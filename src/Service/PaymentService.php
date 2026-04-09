<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PaymentService
{
    private const SESSION_PAYMENT_OTP = 'nexora.payment_otp';
    private const SESSION_PAYMENT_OTP_VERIFIED = 'nexora.payment_otp_verified';
    private const SIMULATED_STORAGE = __DIR__.'/../../var/data/stripe_simulation.json';

    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly LegacyBankingSecurity $security,
        private readonly ActivityService $activityService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function sendPaymentOtp(int $userId, string $phone, SessionInterface $session): ?string
    {
        $normalizedPhone = trim($phone);
        if ($normalizedPhone === '') {
            throw new \InvalidArgumentException('A phone number is required for payment verification.');
        }

        $otp = (string) random_int(100000, 999999);
        $payload = $session->get(self::SESSION_PAYMENT_OTP, []);
        $payload[$userId] = [
            'code' => $otp,
            'phone' => $normalizedPhone,
            'expires_at' => time() + 300,
        ];
        $session->set(self::SESSION_PAYMENT_OTP, $payload);
        $session->remove(self::SESSION_PAYMENT_OTP_VERIFIED);

        if ($this->sendTwilioOtp($normalizedPhone, $otp)) {
            return null;
        }

        return $otp;
    }

    public function verifyPaymentOtp(int $userId, string $phone, string $otp, SessionInterface $session): bool
    {
        if ($this->verifyTwilioOtp(trim($phone), trim($otp))) {
            $session->set(self::SESSION_PAYMENT_OTP_VERIFIED, ['user_id' => $userId, 'verified_at' => time()]);

            return true;
        }

        $payload = $session->get(self::SESSION_PAYMENT_OTP, []);
        $entry = $payload[$userId] ?? null;
        if (!is_array($entry)) {
            return false;
        }

        $valid = trim((string) ($entry['phone'] ?? '')) === trim($phone)
            && (int) ($entry['expires_at'] ?? 0) >= time()
            && trim((string) ($entry['code'] ?? '')) === trim($otp);

        if ($valid) {
            unset($payload[$userId]);
            $session->set(self::SESSION_PAYMENT_OTP, $payload);
            $session->set(self::SESSION_PAYMENT_OTP_VERIFIED, ['user_id' => $userId, 'verified_at' => time()]);
        }

        return $valid;
    }

    public function createCustomer(int $userId, string $name, string $email): array
    {
        $stripe = $this->getStripeClient();
        if ($stripe !== null) {
            $customer = $stripe->customers->create([
                'name' => $name,
                'email' => $email,
                'metadata' => [
                    'user_id' => (string) $userId,
                    'source' => 'nexora-symfony',
                ],
            ]);

            $state = $this->readSimulationState();
            $state['customers'][(string) $userId] = [
                'id' => $customer->id,
                'name' => $name,
                'email' => $email,
                'provider' => 'stripe',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ];
            $this->writeSimulationState($state);

            return $state['customers'][(string) $userId];
        }

        $state = $this->readSimulationState();
        $customer = [
            'id' => 'cus_sim_'.bin2hex(random_bytes(4)),
            'name' => $name,
            'email' => $email,
            'provider' => 'simulation',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $state['customers'][(string) $userId] = $customer;
        $this->writeSimulationState($state);

        return $customer;
    }

    public function createSubscription(int $userId, int $creditId, float $monthlyAmount): array
    {
        if ($monthlyAmount <= 0) {
            throw new \InvalidArgumentException('Subscription amount must be greater than zero.');
        }

        $state = $this->readSimulationState();
        $subscription = [
            'id' => 'sub_sim_'.bin2hex(random_bytes(4)),
            'user_id' => $userId,
            'credit_id' => $creditId,
            'monthly_amount' => round($monthlyAmount, 2),
            'status' => 'active',
            'provider' => $this->getStripeClient() !== null ? 'stripe-ready-simulated' : 'simulation',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $state['subscriptions'][$subscription['id']] = $subscription;
        $this->writeSimulationState($state);
        $this->activityService->log($userId, 'STRIPE_SUBSCRIPTION', 'Symfony portal', sprintf('Subscription %s created for credit #%d.', $subscription['id'], $creditId));

        return $subscription;
    }

    public function cancelSubscription(int $userId, string $subscriptionId): void
    {
        $state = $this->readSimulationState();
        if (!isset($state['subscriptions'][$subscriptionId])) {
            throw new \RuntimeException('Subscription not found.');
        }

        if ((int) ($state['subscriptions'][$subscriptionId]['user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Subscription access denied.');
        }

        $state['subscriptions'][$subscriptionId]['status'] = 'cancelled';
        $state['subscriptions'][$subscriptionId]['cancelled_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->writeSimulationState($state);
        $this->activityService->log($userId, 'STRIPE_SUBSCRIPTION_CANCEL', 'Symfony portal', sprintf('Subscription %s cancelled.', $subscriptionId));
    }

    public function getStripeState(int $userId): array
    {
        $state = $this->readSimulationState();

        return [
            'customer' => $state['customers'][(string) $userId] ?? null,
            'subscriptions' => array_values(array_filter(
                $state['subscriptions'],
                static fn (array $subscription): bool => (int) ($subscription['user_id'] ?? 0) === $userId
            )),
        ];
    }

    public function payCreditInstallment(
        int $userId,
        int $creditId,
        int $accountId,
        float $amount,
        SessionInterface $session,
        string $mode = 'simulation',
        string $paymentMethod = ''
    ): array {
        $verified = $session->get(self::SESSION_PAYMENT_OTP_VERIFIED, []);
        if ((int) ($verified['user_id'] ?? 0) !== $userId || (int) ($verified['verified_at'] ?? 0) < (time() - 900)) {
            throw new \RuntimeException('Payment OTP verification is required before paying a credit installment.');
        }

        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $credit = $this->connection->fetchAssociative('SELECT * FROM credit WHERE idCredit = ? AND idUser = ? LIMIT 1', [$creditId, $userId]);
        $account = $this->connection->fetchAssociative('SELECT * FROM compte WHERE idCompte = ? AND idUser = ? LIMIT 1', [$accountId, $userId]);
        if (!$credit || !$account) {
            throw new \RuntimeException('The selected credit or payment account was not found.');
        }

        $providerResult = $this->chargeProvider($userId, $amount, $mode, $paymentMethod);

        $this->connection->transactional(function () use ($userId, $creditId, $accountId, $amount, $account, $credit, $providerResult): void {
            $newBalance = round(((float) $account['solde']) - $amount, 2);
            if ($newBalance < 0) {
                throw new \RuntimeException('Account balance is too low for this payment.');
            }

            $this->connection->update('compte', [
                'solde' => $newBalance,
            ], [
                'idCompte' => $accountId,
                'idUser' => $userId,
            ]);

            $description = sprintf(
                'Paiement mensualite credit #%d via %s (%s)',
                $creditId,
                strtoupper($providerResult['provider']),
                $providerResult['reference']
            );

            $this->connection->insert('transactions', [
                'idCompte' => $accountId,
                'idUser' => $userId,
                'categorie' => 'Paiement credit',
                'dateTransaction' => date('Y-m-d'),
                'montant' => $this->security->encryptAmount($amount),
                'typeTransaction' => 'DEBIT',
                'statutTransaction' => 'VALIDED',
                'soldeApres' => $newBalance,
                'description' => $description,
                'montantPaye' => $this->security->encryptAmount($amount),
            ]);

            $totalPaid = $this->calculateTotalPaid($userId, $creditId) + $amount;
            $targetAmount = max(
                (float) ($credit['montantDemande'] ?? 0),
                (float) ($credit['mensualite'] ?? 0) * max(1, (int) ($credit['duree'] ?? 1))
            );

            $status = $totalPaid >= $targetAmount ? 'Rembourse' : 'En cours';
            $this->connection->update('credit', [
                'statut' => $status,
            ], [
                'idCredit' => $creditId,
                'idUser' => $userId,
            ]);
        });

        $this->activityService->log($userId, 'CREDIT_PAYMENT', 'Symfony portal', sprintf('Credit #%d paid %.2f DT.', $creditId, $amount));
        $this->notificationService->createNotification(
            $userId,
            null,
            $userId,
            'CREDIT_PAYMENT',
            'Credit installment paid',
            sprintf('A payment of %.2f DT was registered for credit #%d.', $amount, $creditId)
        );

        return [
            'provider' => $providerResult['provider'],
            'reference' => $providerResult['reference'],
            'status' => $providerResult['status'],
            'amount' => $amount,
        ];
    }

    public function getPaymentHistory(int $userId, ?int $creditId = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM transactions
             WHERE idUser = ?
               AND categorie = ?
             ORDER BY idTransaction DESC',
            [$userId, 'Paiement credit']
        );

        $history = [];
        foreach ($rows as $row) {
            if ($creditId !== null && !str_contains((string) ($row['description'] ?? ''), '#'.$creditId)) {
                continue;
            }

            $history[] = [
                'idTransaction' => $row['idTransaction'],
                'dateTransaction' => $row['dateTransaction'],
                'amount' => (float) ($this->security->decryptAmount((string) ($row['montant'] ?? '')) ?? 0.0),
                'description' => (string) ($row['description'] ?? ''),
                'status' => (string) ($row['statutTransaction'] ?? ''),
            ];
        }

        return $history;
    }

    public function calculateTotalPaid(int $userId, ?int $creditId = null): float
    {
        $history = $this->getPaymentHistory($userId, $creditId);

        return round(array_sum(array_column($history, 'amount')), 2);
    }

    public function getWeatherRisk(string $city): array
    {
        $normalized = trim($city);
        if ($normalized === '') {
            $normalized = 'Tunis,TN';
        }

        $apiKey = trim((string) ($_ENV['OPENWEATHER_API_KEY'] ?? $_SERVER['OPENWEATHER_API_KEY'] ?? ''));
        if ($apiKey !== '') {
            try {
                $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                    'query' => [
                        'q' => $normalized,
                        'appid' => $apiKey,
                        'units' => 'metric',
                    ],
                ])->toArray(false);

                $main = $response['main'] ?? [];
                $wind = $response['wind'] ?? [];
                $temp = (float) ($main['temp'] ?? 0);
                $humidity = (float) ($main['humidity'] ?? 0);
                $windSpeed = (float) ($wind['speed'] ?? 0);
                $penalty = 0;

                if ($temp > 35 || $temp < 3) {
                    $penalty += 20;
                }
                if ($humidity < 25 || $humidity > 90) {
                    $penalty += 15;
                }
                if ($windSpeed > 15) {
                    $penalty += 10;
                }

                return [
                    'city' => $normalized,
                    'risk_score' => min(100, $penalty + 20),
                    'message' => sprintf('Weather data loaded for %s. Temperature %.1f C, humidity %.0f%%, wind %.1f m/s.', $normalized, $temp, $humidity, $windSpeed),
                ];
            } catch (\Throwable) {
            }
        }

        $seed = strlen($normalized) % 10;

        return [
            'city' => $normalized,
            'risk_score' => 25 + ($seed * 5),
            'message' => 'Fallback weather risk estimation is active because the live OpenWeather API is not configured.',
        ];
    }

    public function getCurrencyInsight(float $amountTnd, string $ipAddress = ''): array
    {
        $currency = 'TND';
        $country = 'Tunisia';
        $normalizedIp = trim($ipAddress);

        if ($normalizedIp !== '' && !in_array($normalizedIp, ['127.0.0.1', '::1'], true)) {
            try {
                $geo = $this->httpClient->request('GET', 'https://ipapi.co/'.$normalizedIp.'/json/')->toArray(false);
                $currency = strtoupper((string) ($geo['currency'] ?? 'TND')) ?: 'TND';
                $country = (string) ($geo['country_name'] ?? 'Unknown location');
            } catch (\Throwable) {
            }
        }

        $rate = 1.0;
        if ($currency !== 'TND') {
            try {
                $fx = $this->httpClient->request('GET', 'https://api.frankfurter.app/latest', [
                    'query' => [
                        'from' => 'TND',
                        'to' => $currency,
                    ],
                ])->toArray(false);
                $rate = (float) (($fx['rates'][$currency] ?? 1.0));
            } catch (\Throwable) {
                $rate = 1.0;
            }
        }

        return [
            'country' => $country,
            'currency' => $currency,
            'converted_amount' => round($amountTnd * $rate, 2),
            'rate' => round($rate, 4),
        ];
    }

    private function chargeProvider(int $userId, float $amount, string $mode, string $paymentMethod): array
    {
        $stripe = $this->getStripeClient();
        $mode = strtolower(trim($mode));

        if ($stripe !== null && $mode === 'stripe' && trim($paymentMethod) !== '') {
            $intent = $stripe->paymentIntents->create([
                'amount' => max(1, (int) round($amount * 100)),
                'currency' => 'usd',
                'payment_method' => trim($paymentMethod),
                'confirm' => true,
                'metadata' => [
                    'user_id' => (string) $userId,
                    'source' => 'nexora-symfony',
                ],
            ]);

            return [
                'provider' => 'stripe',
                'reference' => $intent->id,
                'status' => (string) $intent->status,
            ];
        }

        return [
            'provider' => 'simulation',
            'reference' => 'pay_sim_'.bin2hex(random_bytes(4)),
            'status' => 'succeeded',
        ];
    }

    private function sendTwilioOtp(string $phone, string $otp): bool
    {
        $serviceSid = trim((string) ($_ENV['TWILIO_SERVICE_SID'] ?? $_SERVER['TWILIO_SERVICE_SID'] ?? ''));
        $accountSid = trim((string) ($_ENV['TWILIO_ACCOUNT_SID'] ?? $_SERVER['TWILIO_ACCOUNT_SID'] ?? ''));
        $authToken = trim((string) ($_ENV['TWILIO_AUTH_TOKEN'] ?? $_SERVER['TWILIO_AUTH_TOKEN'] ?? ''));

        if ($serviceSid === '' || $accountSid === '' || $authToken === '') {
            return false;
        }

        try {
            $this->httpClient->request('POST', sprintf('https://verify.twilio.com/v2/Services/%s/Verifications', $serviceSid), [
                'auth_basic' => [$accountSid, $authToken],
                'body' => [
                    'To' => $phone,
                    'Channel' => 'sms',
                ],
            ])->getStatusCode();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function verifyTwilioOtp(string $phone, string $otp): bool
    {
        $serviceSid = trim((string) ($_ENV['TWILIO_SERVICE_SID'] ?? $_SERVER['TWILIO_SERVICE_SID'] ?? ''));
        $accountSid = trim((string) ($_ENV['TWILIO_ACCOUNT_SID'] ?? $_SERVER['TWILIO_ACCOUNT_SID'] ?? ''));
        $authToken = trim((string) ($_ENV['TWILIO_AUTH_TOKEN'] ?? $_SERVER['TWILIO_AUTH_TOKEN'] ?? ''));

        if ($serviceSid === '' || $accountSid === '' || $authToken === '') {
            return false;
        }

        try {
            $data = $this->httpClient->request('POST', sprintf('https://verify.twilio.com/v2/Services/%s/VerificationCheck', $serviceSid), [
                'auth_basic' => [$accountSid, $authToken],
                'body' => [
                    'To' => $phone,
                    'Code' => $otp,
                ],
            ])->toArray(false);

            return strtolower((string) ($data['status'] ?? '')) === 'approved';
        } catch (\Throwable) {
            return false;
        }
    }

    private function getStripeClient(): ?StripeClient
    {
        $secret = $this->getStripeSecretKey();
        if ($secret === '') {
            return null;
        }

        return new StripeClient($secret);
    }

    private function getStripeSecretKey(): string
    {
        return trim((string) ($_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY'] ?? ''));
    }

    private function readSimulationState(): array
    {
        if (!is_file(self::SIMULATED_STORAGE)) {
            return [
                'customers' => [],
                'subscriptions' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents(self::SIMULATED_STORAGE), true);

        return is_array($decoded)
            ? array_merge(['customers' => [], 'subscriptions' => []], $decoded)
            : ['customers' => [], 'subscriptions' => []];
    }

    private function writeSimulationState(array $state): void
    {
        $directory = dirname(self::SIMULATED_STORAGE);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(self::SIMULATED_STORAGE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
