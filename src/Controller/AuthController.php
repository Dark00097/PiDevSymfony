<?php

namespace App\Controller;

use App\Service\AdminSecuritySettingsService;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    private const LOGIN_FAILED_ATTEMPTS = 'nexora.login_failed_attempts';
    private const LOGIN_MATH_QUESTION = 'nexora.login_math_question';
    private const LOGIN_MATH_ANSWER = 'nexora.login_math_answer';

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthService $authService, AdminSecuritySettingsService $adminSecuritySettingsService): Response
    {
        $session = $request->getSession();
        $authenticatedUser = $authService->getAuthenticatedUser($session);
        if ($authenticatedUser !== null) {
            $blockedReason = $authService->getLoginBlockReason($authenticatedUser);
            if ($blockedReason === null) {
                return $this->redirectToRoute($authService->getAuthenticatedRole($session) === 'ROLE_ADMIN' ? 'admin_dashboard' : 'portal_dashboard');
            }

            $authService->logoutUser($session);
            $this->addFlash('error', $blockedReason);

            return $this->redirectToRoute('login');
        }

        $failedAttempts = (int) $session->get(self::LOGIN_FAILED_ATTEMPTS, 0);
        $mathRequired = $failedAttempts >= 3;

        if ($request->isMethod('POST')) {
            $loginMode = trim((string) $request->request->get('login_mode', 'password'));
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $faceDescriptor = (string) $request->request->get('face_descriptor', '');

            if ($email === '') {
                $this->addFlash('error', 'Please fill your email.');
            } elseif (!str_contains($email, '@')) {
                $this->addFlash('error', 'Please enter a valid email address.');
            } elseif ($mathRequired && !$this->verifyMathChallenge($request)) {
                $this->generateMathChallenge($request);
                $this->addFlash('error', 'Too many failed attempts. Please solve the math challenge.');
            } else {
                $candidateUser = $authService->findUserByEmail($email);
                if ($candidateUser !== null) {
                    $candidateBlockedReason = $authService->getLoginBlockReason($candidateUser);
                    if ($candidateBlockedReason !== null) {
                        $this->addFlash('error', $candidateBlockedReason);

                        return $this->redirectToRoute('login');
                    }
                }

                $user = null;
                if ($loginMode === 'face') {
                    if (trim($faceDescriptor) === '') {
                        $this->addFlash('error', 'Face template is missing. Capture your face first.');
                    } else {
                        $user = $authService->authenticateByFace($email, $faceDescriptor, $request);
                    }
                } else {
                    if ($password === '') {
                        $this->addFlash('error', 'Please fill email and password.');
                    } else {
                        $user = $authService->authenticate($email, $password, $request);
                    }
                }

                if ($user === null) {
                    ++$failedAttempts;
                    $session->set(self::LOGIN_FAILED_ATTEMPTS, $failedAttempts);
                    if ($failedAttempts >= 3) {
                        $this->generateMathChallenge($request);
                    }
                    $this->addFlash('error', $loginMode === 'face' ? 'Face verification failed.' : 'Invalid email or password.');
                } else {
                    $role = strtoupper(trim((string) ($user['role'] ?? '')));
                    $adminSettings = $adminSecuritySettingsService->getSettings();
                    $faceRequired = $authService->isFaceLoginRequired(
                        $user,
                        $role === 'ROLE_ADMIN' && (bool) ($adminSettings['require_biometric_on_admin_login'] ?? false)
                    );
                    $blockedReason = $authService->getLoginBlockReason($user);

                    if ($blockedReason !== null) {
                        $this->addFlash('error', $blockedReason);
                    } elseif ($faceRequired && $loginMode !== 'face') {
                        $this->addFlash('error', 'Face login is required for this account. Use "Connexion par visage".');
                    } else {
                        $session->remove(self::LOGIN_FAILED_ATTEMPTS);
                        $session->remove(self::LOGIN_MATH_QUESTION);
                        $session->remove(self::LOGIN_MATH_ANSWER);
                        $authService->loginUser($session, $user);

                        return $this->redirectToRoute($role === 'ROLE_ADMIN' ? 'admin_dashboard' : 'portal_dashboard');
                    }
                }
            }

            $failedAttempts = (int) $session->get(self::LOGIN_FAILED_ATTEMPTS, 0);
            $mathRequired = $failedAttempts >= 3;

            return $this->redirectToRoute('login');
        }

        return $this->render('auth/login.html.twig', [
            'math_required' => $mathRequired,
            'math_question' => $session->get(self::LOGIN_MATH_QUESTION),
        ]);
    }

    #[Route('/signup', name: 'signup', methods: ['GET', 'POST'])]
    public function signup(Request $request, AuthService $authService): Response
    {
        if ($request->isMethod('POST')) {
            $action = trim((string) $request->request->get('action', ''));
            if ($action === '') {
                $action = trim((string) $request->request->get('full_name', '')) !== '' ? 'signup' : 'send_otp';
            }
            $email = trim((string) $request->request->get('email', ''));
            $phone = trim((string) $request->request->get('telephone', ''));

            if ($action === 'send_otp') {
                try {
                    $authService->sendSignupOtp($email, $request->getSession(), $phone);
                    $this->addFlash('success', 'OTP sent to your email and SMS (if SMS is configured). It expires in 5 minutes.');
                } catch (\Throwable $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }

            if ($action === 'signup') {
                $fullName = trim((string) $request->request->get('full_name', ''));
                $otp = trim((string) $request->request->get('otp', ''));
                if ($otp === '') {
                    $otp = $this->collectOtpDigitsFromRequest($request);
                }
                $password = (string) $request->request->get('password', '');
                $confirmPassword = (string) $request->request->get('confirm_password', '');

                if ($fullName === '' || $email === '' || $phone === '' || $otp === '' || $password === '' || $confirmPassword === '') {
                    $this->addFlash('error', 'Please complete all fields.');
                } elseif (strlen($password) < 8) {
                    $this->addFlash('error', 'Password must be at least 8 characters.');
                } elseif ($password !== $confirmPassword) {
                    $this->addFlash('error', 'Passwords do not match.');
                } elseif (!$authService->verifySignupOtp($email, $otp, $request->getSession(), $phone)) {
                    $this->addFlash('error', 'Invalid or expired OTP.');
                } else {
                    [$nom, $prenom] = $this->splitName($fullName);

                    try {
                        $authService->registerUser([
                            'nom' => $nom,
                            'prenom' => $prenom,
                            'email' => $email,
                            'telephone' => $phone,
                            'password' => $password,
                        ], $request);
                        $this->addFlash('success', 'Your account was created and is pending admin approval.');

                        return $this->redirectToRoute('login');
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }
                }
            }
        }

        return $this->render('auth/signup.html.twig');
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, AuthService $authService): Response
    {
        $session = $request->getSession();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');
            $email = trim((string) $request->request->get('email', ''));

            if ($action === 'send_otp') {
                try {
                    $authService->sendPasswordResetOtp($email, $session);
                    $session->set('nexora.reset_email', $email);
                    $this->addFlash('success', 'OTP sent to '.$email.'.');
                } catch (\Throwable $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }

            if ($action === 'verify_otp') {
                $otp = trim((string) $request->request->get('otp', ''));
                try {
                    if ($authService->verifyPasswordResetOtp($email, $otp, $session)) {
                        $session->set('nexora.reset_email', $email);
                        $this->addFlash('success', 'OTP verified. You can now set a new password.');
                    } else {
                        $this->addFlash('error', 'Invalid or expired OTP.');
                    }
                } catch (\Throwable $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }
            }

            if ($action === 'reset_password') {
                $password = (string) $request->request->get('new_password', '');
                $confirmPassword = (string) $request->request->get('confirm_password', '');
                $email = $email !== '' ? $email : (string) $session->get('nexora.reset_email', '');

                if ($password === '' || $confirmPassword === '') {
                    $this->addFlash('error', 'Please fill password fields.');
                } elseif ($password !== $confirmPassword) {
                    $this->addFlash('error', 'Passwords do not match.');
                } elseif (strlen($password) < 8) {
                    $this->addFlash('error', 'New password must be at least 8 characters.');
                } else {
                    try {
                        $authService->resetPasswordByVerifiedEmail($email, $password, $session);
                        $session->remove('nexora.reset_email');
                        $this->addFlash('success', 'Your password was updated successfully.');

                        return $this->redirectToRoute('login');
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', $exception->getMessage());
                    }
                }
            }
        }

        return $this->render('auth/forgot_password.html.twig', [
            'reset_email' => (string) $session->get('nexora.reset_email', ''),
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(Request $request, AuthService $authService): RedirectResponse
    {
        $authService->logoutUser($request->getSession());

        return $this->redirectToRoute('home');
    }

    private function splitName(string $fullName): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($fullName)) ?? trim($fullName);
        $parts = explode(' ', $normalized, 2);

        return [$parts[0] ?? '-', $parts[1] ?? '-'];
    }

    private function collectOtpDigitsFromRequest(Request $request): string
    {
        $otp = '';
        for ($index = 1; $index <= 6; ++$index) {
            $digit = preg_replace('/\D+/', '', (string) $request->request->get('otp_digit_'.$index, '')) ?? '';
            if ($digit === '') {
                continue;
            }

            $otp .= substr($digit, -1);
        }

        return substr($otp, 0, 6);
    }

    private function generateMathChallenge(Request $request): void
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $request->getSession()->set(self::LOGIN_MATH_QUESTION, sprintf('Security check: %d + %d = ?', $left, $right));
        $request->getSession()->set(self::LOGIN_MATH_ANSWER, $left + $right);
    }

    private function verifyMathChallenge(Request $request): bool
    {
        $answer = (int) $request->request->get('math_answer', 0);

        return $answer > 0 && $answer === (int) $request->getSession()->get(self::LOGIN_MATH_ANSWER, -1);
    }
}
