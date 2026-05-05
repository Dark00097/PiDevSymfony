<?php

namespace App\Controller\Sections;

use App\Domain\Garantie\DocumentVerificationStatus;
use App\Entity\Garantiecredit;
use App\Service\ActivityService;
use App\Service\BankingService;
use App\Service\CloudinaryStorageService;
use App\Service\ExportService;
use App\Service\FraudDetectionService;
use App\Service\GarantieDocumentWorkflowService;
use App\Service\NotificationService;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class GarantiesController
{

    public function __construct(
        private readonly FraudDetectionService $fraudDetectionService,
        private readonly GarantieDocumentWorkflowService $documentWorkflowService,
        private readonly CloudinaryStorageService $cloudinaryStorageService,
        private readonly NotificationService $notificationService,
    ) {
    }
    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function buildAdminData(BankingService $bankingService): array
    {
        $garanties = $this->decorateGaranties($bankingService->listGaranties());

        return [
            'items' => $garanties,
            'support' => [
                'garanties'           => $garanties,
                'credits'             => $bankingService->listCredits(),
                'garantie_type_stats' => $bankingService->getGarantieTypeDistribution(),
                'garantie_stats'      => $this->buildGarantieStats($garanties),
                'garantie_type_choices' => Garantiecredit::getTypeChoices(),
                'document_statuses'   => $this->documentWorkflowService->allowedStatuses(),
            ],
        ];
    }

    public function handleAdminAction(string $action, Request $request, BankingService $bankingService): ?array
    {
        switch ($action) {
            case 'garantie_save':
                $data = $request->request->all();
                $normalizedType = $this->normalizeGarantieTypeValue((string) ($data['typeGarantie'] ?? ''));
                if ($normalizedType !== null) {
                    $data['typeGarantie'] = $normalizedType;
                }
                $uploadedDocument = $request->files->get('documentJustificatifFile');
                if (!$uploadedDocument instanceof UploadedFile) {
                    $uploadedDocument = $request->files->get('documentJustificatif');
                }
                if ($uploadedDocument instanceof UploadedFile) {
                    $data['documentJustificatifFile'] = $uploadedDocument;
                }
                $validationUserId = (int) ($data['idUser'] ?? 0);
                $errors = $this->validateGarantie($data, $bankingService, $validationUserId > 0 ? $validationUserId : 0);
                if ($errors !== []) {
                    return [
                        'type' => 'validation_error',
                        'message' => implode(' ', array_values($errors)),
                    ];
                }

                if ($uploadedDocument instanceof UploadedFile && $uploadedDocument->isValid()) {
                    try {
                        $cloudinaryDoc = $this->cloudinaryStorageService->uploadGuaranteeDocument(
                            $uploadedDocument,
                            $validationUserId,
                            $this->requestInt($request, 'idGarantie')
                        );
                        $data['documentJustificatif'] = $cloudinaryDoc['url'];
                        $data['documentUrl'] = $cloudinaryDoc['url'];
                        $data['documentPublicId'] = $cloudinaryDoc['public_id'];
                        $data['documentMimeType'] = $cloudinaryDoc['mime_type'];
                        $data['documentUploadedAt'] = $cloudinaryDoc['uploaded_at'];
                        $data['existingDocumentJustificatif'] = $cloudinaryDoc['url'];
                        $data['statutVerificationDocument'] = $this->documentWorkflowService->initialStatus();
                        $data['statutDocument'] = $this->documentStatusFromVerificationStatus((string) $data['statutVerificationDocument']);
                    } catch (\Throwable $exception) {
                        return [
                            'type' => 'error',
                            'message' => 'Upload document impossible: '.$exception->getMessage(),
                        ];
                    }
                } else {
                    $existingDocument = trim((string) ($data['existingDocumentJustificatif'] ?? ($data['existing_document'] ?? '')));
                    if ($existingDocument !== '' && trim((string) ($data['documentJustificatif'] ?? '')) === '') {
                        $data['documentJustificatif'] = $existingDocument;
                    }
                }
                unset($data['documentJustificatifFile']);
                $bankingService->saveGarantie($data, $this->requestInt($request, 'idGarantie'));
                $this->logHistory($request, 'garantie_save', $data);

                return ['type' => 'success', 'message' => 'Garantie saved.'];

            case 'garantie_delete':
                $id = $this->requestInt($request, 'idGarantie') ?? 0;
                $bankingService->deleteGarantie($id);
                $this->logHistory($request, 'garantie_delete', ['idGarantie' => $id]);

                return ['type' => 'success', 'message' => 'Garantie deleted.'];
            case 'garantie_document_review':
                $garantieId = $this->requestInt($request, 'idGarantie') ?? 0;
                if ($garantieId <= 0) {
                    return ['type' => 'error', 'message' => 'Garantie introuvable.'];
                }
                $requestedDocumentStatus = $this->normalizeDocumentStatus((string) $request->request->get('statutDocument', ''));
                $requestedVerificationStatus = (string) $request->request->get('statutVerificationDocument', '');
                if (trim($requestedVerificationStatus) === '' && $requestedDocumentStatus === 'refuse') {
                    $requestedVerificationStatus = DocumentVerificationStatus::REJETE;
                } elseif (trim($requestedVerificationStatus) === '' && $requestedDocumentStatus === 'valide') {
                    $requestedVerificationStatus = DocumentVerificationStatus::VALIDE;
                }
                $status = $this->documentWorkflowService->resolveStatus($requestedVerificationStatus);
                $remark = trim((string) $request->request->get('remarqueAdmin', ''));
                $garantie = $this->findGarantieById($bankingService->listGaranties(), $garantieId);
                if ($garantie === null) {
                    return ['type' => 'error', 'message' => 'Garantie introuvable.'];
                }
                $currentStatus = (string) ($garantie['statutVerificationDocument'] ?? DocumentVerificationStatus::EN_ATTENTE);
                if (!$this->documentWorkflowService->canTransition($currentStatus, $status)) {
                    return ['type' => 'error', 'message' => 'Transition de statut document non autorisee.'];
                }
                $payload = $garantie;
                $payload['statutVerificationDocument'] = $status;
                $payload['statutDocument'] = $this->documentStatusFromVerificationStatus($status);
                $payload['remarqueAdmin'] = $remark;
                $bankingService->saveGarantie($payload, $garantieId);
                $this->notifyGarantieOwnerByMailAndSms($bankingService, $payload, sprintf(
                    'Le statut de votre justificatif de garantie #%d est maintenant: %s.',
                    $garantieId,
                    DocumentVerificationStatus::label($status)
                ));

                return ['type' => 'success', 'message' => 'Statut du document mis a jour.'];
            case 'garantie_quick_status':
                $garantieId = $this->requestInt($request, 'idGarantie') ?? 0;
                if ($garantieId <= 0) {
                    return ['type' => 'error', 'message' => 'Garantie introuvable.'];
                }
                $garantie = $this->findGarantieById($bankingService->listGaranties(), $garantieId);
                if ($garantie === null) {
                    return ['type' => 'error', 'message' => 'Garantie introuvable.'];
                }
                $nextStatus = trim((string) $request->request->get('statut', ''));
                $allowed = ['En attente', 'Actif', 'Rejete'];
                if (!in_array($nextStatus, $allowed, true)) {
                    return ['type' => 'error', 'message' => 'Statut non autorise.'];
                }

                $payload = $garantie;
                $payload['statut'] = $nextStatus;
                if ($nextStatus === 'Actif') {
                    $payload['statutVerificationDocument'] = DocumentVerificationStatus::VALIDE;
                    $payload['statutDocument'] = 'valide';
                } elseif ($nextStatus === 'Rejete') {
                    $payload['statutVerificationDocument'] = DocumentVerificationStatus::REJETE;
                    $payload['statutDocument'] = 'refuse';
                }
                $bankingService->saveGarantie($payload, $garantieId);
                $this->notifyGarantieOwnerByMailAndSms($bankingService, $payload, sprintf(
                    'Le statut de votre garantie #%d a ete mis a jour: %s.',
                    $garantieId,
                    $nextStatus
                ));

                return ['type' => 'success', 'message' => 'Statut de garantie mis a jour.'];
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Portal
    // -------------------------------------------------------------------------

    public function buildPortalData(BankingService $bankingService, int $userId, ?Request $request = null): array
    {
        $allGaranties    = $this->decorateGaranties(
            $this->enrichGarantiesWithFraudData($bankingService->listGaranties($userId), $userId)
        );
        $garantieQuery   = $this->resolvePortalGarantieQuery($request);
        $garanties       = $this->filterAndSortPortalGaranties($allGaranties, $garantieQuery);

        $formErrorsGarantie = $request !== null ? $this->getFormErrors($request, 'garantie') : [];
        $formDataGarantie   = $request !== null ? $this->getFormData($request, 'garantie') : [];

        if ($request !== null) {
            $request->getSession()->remove('nexora.form_errors.garantie');
            $request->getSession()->remove('nexora.form_data.garantie');
        }

        return [
            'items' => $garanties,
            'support' => [
                'garanties'                => $garanties,
                'all_garanties'            => $allGaranties,
                'credits'                  => $bankingService->listCredits($userId),
                'garantie_query'           => $garantieQuery,
                'garantie_filter_counts'   => $this->buildPortalGarantieStatusCounts($allGaranties),
                'filtered_garantie_count'  => count($garanties),
                'garantie_history'         => $request !== null ? $this->getHistory($request) : [],
                'garantie_stats'           => $this->buildGarantieStats($allGaranties),
                'garantie_fraud_stats'     => $this->buildFraudStats($allGaranties),
                'garantie_fraud_history'   => $this->extractFraudHistoryMap($allGaranties),
                'form_errors_garantie'     => $formErrorsGarantie,
                'form_data_garantie'       => $formDataGarantie,
                'garantie_form_feedback'   => [
                    'errors' => $formErrorsGarantie,
                    'input' => $formDataGarantie,
                ],
                'document_statuses'        => $this->documentWorkflowService->allowedStatuses(),
            ],
        ];
    }

    public function handlePortalAction(string $action, Request $request, BankingService $bankingService, int $userId): ?array
    {
        switch ($action) {
            case 'garantie_save':
                $data = $request->request->all();
                $normalizedType = $this->normalizeGarantieTypeValue((string) ($data['typeGarantie'] ?? ''));
                if ($normalizedType !== null) {
                    $data['typeGarantie'] = $normalizedType;
                }
                $uploadedOriginalName = '';
                $uploadedDocument = $request->files->get('documentJustificatifFile');
                if ($uploadedDocument instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    $uploadedOriginalName = trim((string) $uploadedDocument->getClientOriginalName());
                    $data['documentJustificatifFile'] = $uploadedDocument;
                }

                if (($data['idUser'] ?? '') === '') {
                    $data['idUser'] = (string) $userId;
                }

                $errors = $this->validateGarantie($data, $bankingService, $userId);
                if ($errors !== []) {
                    $sessionFormData = $data;
                    unset($sessionFormData['documentJustificatifFile']);
                    $request->getSession()->set('nexora.form_errors.garantie', $errors);
                    $request->getSession()->set('nexora.form_data.garantie', $sessionFormData);
                    $firstError = (string) (array_values($errors)[0] ?? 'Erreur de validation.');

                    return ['type' => 'validation_error', 'message' => 'Erreur garantie: '.$firstError];
                }

                $request->getSession()->remove('nexora.form_errors.garantie');
                $request->getSession()->remove('nexora.form_data.garantie');

                if ($uploadedDocument instanceof UploadedFile && $uploadedDocument->isValid()) {
                    try {
                        $cloudinaryDoc = $this->cloudinaryStorageService->uploadGuaranteeDocument(
                            $uploadedDocument,
                            $userId,
                            $this->requestInt($request, 'idGarantie')
                        );
                    } catch (\Throwable $exception) {
                        return [
                            'type' => 'error',
                            'message' => 'Upload Cloudinary impossible: '.$exception->getMessage(),
                        ];
                    }

                    $data['documentJustificatif'] = $cloudinaryDoc['url'];
                    $data['documentUrl'] = $cloudinaryDoc['url'];
                    $data['documentPublicId'] = $cloudinaryDoc['public_id'];
                    $data['documentMimeType'] = $cloudinaryDoc['mime_type'];
                    $data['documentUploadedAt'] = $cloudinaryDoc['uploaded_at'];
                    $data['existingDocumentJustificatif'] = $cloudinaryDoc['url'];
                    $data['statutVerificationDocument'] = $this->documentWorkflowService->initialStatus();
                    $data['statutDocument'] = $this->documentStatusFromVerificationStatus((string) $data['statutVerificationDocument']);
                }
                unset($data['documentJustificatifFile']);

                try {
                    $garantieId = $this->requestInt($request, 'idGarantie');
                    if ($garantieId !== null) {
                        $existingGarantie = null;
                        foreach ($bankingService->listGaranties($userId) as $garantie) {
                            if ((int) ($garantie['idGarantie'] ?? 0) === $garantieId) {
                                $existingGarantie = $garantie;
                                break;
                            }
                        }
                        if ($existingGarantie === null) {
                            return ['type' => 'error', 'message' => 'Garantie introuvable ou inaccessible.'];
                        }
                        $data['idUser'] = (string) $userId;
                        $savedGarantieId = $bankingService->saveGarantie($data, $garantieId, $userId);
                    } else {
                        $savedGarantieId = $bankingService->saveGarantie($data, null, $userId);
                    }

                    $analysisPayload = array_replace($data, [
                        'idGarantie' => $savedGarantieId,
                        'uploadedOriginalName' => $uploadedOriginalName,
                    ]);
                    $analysis = $this->fraudDetectionService->analyzeGuarantee($analysisPayload, $userId);
                    $this->fraudDetectionService->recordGuaranteeAnalysis(
                        $userId,
                        (int) $savedGarantieId,
                        (string) ($analysis['document_name'] ?? ($data['documentJustificatif'] ?? '')),
                        $analysis
                    );
                } catch (\Throwable $exception) {
                    return [
                        'type' => 'error',
                        'message' => 'Erreur enregistrement garantie: '.$exception->getMessage(),
                    ];
                }

                $this->logHistory($request, 'garantie_save', $data);

                return [
                    'type' => 'success',
                    'message' => 'Garantie enregistree. '.$this->documentWorkflowService->userMessage(
                        (string) ($data['statutVerificationDocument'] ?? DocumentVerificationStatus::EN_ATTENTE),
                        (string) ($data['remarqueAdmin'] ?? '')
                    ),
                ];

            case 'garantie_delete':
                $id = $this->requestInt($request, 'idGarantie') ?? 0;
                if ($id > 0) {
                    $existingGarantie = null;
                    foreach ($bankingService->listGaranties($userId) as $garantie) {
                        if ((int) ($garantie['idGarantie'] ?? 0) === $id) {
                            $existingGarantie = $garantie;
                            break;
                        }
                    }
                    if ($existingGarantie === null) {
                        return ['type' => 'error', 'message' => 'Garantie introuvable ou inaccessible.'];
                    }
                    $bankingService->deleteGarantie($id, null);
                }
                $this->logHistory($request, 'garantie_delete', ['idGarantie' => $id]);

                return ['type' => 'success', 'message' => 'Garantie supprimée.'];
        }

        return null;
    }

    /**
     * Validates garantie data. Returns field-keyed error messages.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validateGarantie(array $data, BankingService $bankingService, int $userId): array
    {
        $errors = [];
        $today = new \DateTimeImmutable('today');
        $typeGarantie = trim((string) ($data['typeGarantie'] ?? ''));
        if ($typeGarantie === '') {
            $errors['typeGarantie'] = 'Le type de garantie est obligatoire.';
        } elseif ($this->normalizeGarantieTypeValue($typeGarantie) === null) {
            $errors['typeGarantie'] = 'Veuillez selectionner un type de garantie valide.';
        }

        $creditId = (int) ($data['idCredit'] ?? 0);
        if ($creditId > 0) {
            $creditFound = false;
            $credits = $userId > 0 ? $bankingService->listCredits($userId) : $bankingService->listCredits();
            foreach ($credits as $credit) {
                if ((int) ($credit['idCredit'] ?? 0) === $creditId) {
                    $creditFound = true;
                    break;
                }
            }

            if (!$creditFound) {
                $errors['idCredit'] = 'Le credit associe est introuvable.';
            }
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = 'La description est obligatoire.';
        } elseif (mb_strlen($description, 'UTF-8') < 10) {
            $errors['description'] = 'La description doit contenir au moins 10 caracteres.';
        } elseif (mb_strlen($description, 'UTF-8') > 1000) {
            $errors['description'] = 'La description ne peut pas depasser 1000 caracteres.';
        }

        $adresseBien = trim((string) ($data['adresseBien'] ?? ''));
        if ($adresseBien === '') {
            $errors['adresseBien'] = "L'adresse du bien est obligatoire.";
        } elseif (mb_strlen($adresseBien, 'UTF-8') < 5) {
            $errors['adresseBien'] = "L'adresse doit contenir au moins 5 caracteres.";
        } elseif (mb_strlen($adresseBien, 'UTF-8') > 255) {
            $errors['adresseBien'] = "L'adresse du bien ne peut pas depasser 255 caracteres.";
        }

        $adresseComplete = trim((string) ($data['adresseComplete'] ?? ''));
        if ($adresseComplete === '' && $adresseBien !== '') {
            $data['adresseComplete'] = $adresseBien;
            $adresseComplete = $adresseBien;
        }
        if ($adresseComplete !== '' && mb_strlen($adresseComplete, 'UTF-8') > 255) {
            $errors['adresseComplete'] = "L'adresse complete ne peut pas depasser 255 caracteres.";
        }

        $ville = trim((string) ($data['ville'] ?? ''));
        if ($ville !== '' && mb_strlen($ville, 'UTF-8') > 120) {
            $errors['ville'] = 'La ville ne peut pas depasser 120 caracteres.';
        }

        $codePostal = trim((string) ($data['codePostal'] ?? ''));
        if ($codePostal !== '' && mb_strlen($codePostal, 'UTF-8') > 30) {
            $errors['codePostal'] = 'Le code postal ne peut pas depasser 30 caracteres.';
        }

        $pays = trim((string) ($data['pays'] ?? ''));
        if ($pays !== '' && mb_strlen($pays, 'UTF-8') > 120) {
            $errors['pays'] = 'Le pays ne peut pas depasser 120 caracteres.';
        }

        $latitude = trim((string) ($data['latitude'] ?? ''));
        if ($latitude !== '' && (!is_numeric($latitude) || (float) $latitude < -90 || (float) $latitude > 90)) {
            $errors['latitude'] = 'Latitude invalide.';
        }

        $longitude = trim((string) ($data['longitude'] ?? ''));
        if ($longitude !== '' && (!is_numeric($longitude) || (float) $longitude < -180 || (float) $longitude > 180)) {
            $errors['longitude'] = 'Longitude invalide.';
        }

        $data['statutVerificationAdresse'] = ($adresseComplete !== '' && $latitude !== '' && $longitude !== '')
            ? 'Verifiee'
            : 'A verifier';

        $estimatedRaw = $data['valeurEstimee'] ?? '';
        $estimated = (float) $estimatedRaw;
        if ($estimatedRaw === '' || $estimatedRaw === null) {
            $errors['valeurEstimee'] = 'La valeur estimee est obligatoire.';
        } elseif ($estimated <= 0) {
            $errors['valeurEstimee'] = 'La valeur estimee doit etre un nombre positif.';
        } elseif ($estimated < 1000 || $estimated > 100000000) {
            $errors['valeurEstimee'] = 'La valeur estimee doit etre comprise entre 1000 et 100000000 DT.';
        }

        $retainedRaw = $data['valeurRetenue'] ?? '';
        if ($retainedRaw !== '' && $retainedRaw !== null) {
            $retained = (float) $retainedRaw;
            if ($retained <= 0) {
                $errors['valeurRetenue'] = 'La valeur retenue doit etre positive.';
            } elseif ($estimated > 0 && $retained > $estimated) {
                $errors['valeurRetenue'] = 'La valeur retenue ne peut pas depasser la valeur estimee.';
            }
        }

        $dateEvaluation = trim((string) ($data['dateEvaluation'] ?? ''));
        $isEdit = (int) ($data['idGarantie'] ?? 0) > 0;
        if ($dateEvaluation === '') {
            $errors['dateEvaluation'] = "La date d'evaluation est obligatoire.";
        } else {
            try {
                $parsedDate = new \DateTimeImmutable($dateEvaluation);
                if (!$isEdit && $parsedDate < $today) {
                    $errors['dateEvaluation'] = "La date d'evaluation ne peut pas etre dans le passe.";
                }
            } catch (\Throwable) {
                $errors['dateEvaluation'] = "Date d'evaluation invalide.";
            }
        }

        $documentFile = $data['documentJustificatifFile'] ?? null;
        $existingDocument = trim((string) ($data['existingDocumentJustificatif'] ?? ($data['existing_document'] ?? '')));
        $storedDocument = trim((string) ($data['documentJustificatif'] ?? ''));
        if ($documentFile instanceof UploadedFile && $documentFile->isValid()) {
            $extension = strtolower((string) $documentFile->getClientOriginalExtension());
            $mimeType = strtolower((string) ($documentFile->getMimeType() ?? ''));
            $originalName = strtolower(trim((string) $documentFile->getClientOriginalName()));
            $size = (int) ($documentFile->getSize() ?? 0);
            $realMimeType = strtolower((string) (@mime_content_type($documentFile->getPathname()) ?: ''));
            $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $isPdf = $realMimeType === 'application/pdf' || ($mimeType === 'application/pdf' && $extension === 'pdf');
            $isImage = in_array($realMimeType, ['image/jpeg', 'image/png'], true)
                || (in_array($mimeType, ['image/jpeg', 'image/png'], true) && in_array($extension, ['jpg', 'jpeg', 'png'], true));
            $looksLikeScreenshot = (bool) preg_match('/(screen|screenshot|capture|captur|ecran|scrn|snip|printscreen|whatsapp)/i', $originalName);
            $suspiciousName = (bool) preg_match('/(\.\.|[<>:"|?*]|(php|phtml|phar|js|exe|sh|bat|cmd)\b)/i', $originalName);
            $hasAllowedMime = in_array($realMimeType, $allowedMimeTypes, true) || in_array($mimeType, $allowedMimeTypes, true);
            $hasAllowedExtension = in_array($extension, $allowedExtensions, true);

            if (!$hasAllowedMime || !$hasAllowedExtension || !($isPdf || $isImage)) {
                $errors['documentJustificatif'] = 'Format non autorise. Utilisez uniquement JPG, PNG ou PDF.';
            } elseif ($suspiciousName) {
                $errors['documentJustificatif'] = 'Nom de fichier suspect. Renommez le document puis reessayez.';
            } elseif ($looksLikeScreenshot) {
                $errors['documentJustificatif'] = 'Capture d ecran interdite. Veuillez televerser un document officiel.';
            } elseif ($size > 0 && $size < 25_000) {
                $errors['documentJustificatif'] = 'Le fichier est trop petit et semble illisible (minimum 25 Ko).';
            } elseif ($documentFile->getSize() !== null && $documentFile->getSize() > 5_242_880) {
                $errors['documentJustificatif'] = 'Le document est trop volumineux (max. 5 Mo).';
            } elseif ($isImage) {
                $imageInfo = @getimagesize($documentFile->getPathname());
                $width = is_array($imageInfo) ? (int) ($imageInfo[0] ?? 0) : 0;
                $height = is_array($imageInfo) ? (int) ($imageInfo[1] ?? 0) : 0;
                if ($width < 500 || $height < 500) {
                    $errors['documentJustificatif'] = 'Image trop petite: minimum 500x500 px pour garantir la lisibilite.';
                }
            }

            if (!isset($errors['documentJustificatif'])) {
                $data['documentJustificatif'] = $documentFile->getClientOriginalName();
            } else {
                unset($data['documentJustificatif']);
            }
        } else {
            $data['documentJustificatif'] = $storedDocument !== '' ? $storedDocument : $existingDocument;
        }

        $document = trim((string) ($data['documentJustificatif'] ?? ''));
        if ($document === '') {
            $errors['documentJustificatif'] = 'Le document justificatif est obligatoire.';
        } elseif (mb_strlen($document, 'UTF-8') > 255) {
            $errors['documentJustificatif'] = 'Le nom du document ne peut pas depasser 255 caracteres.';
        }

        $nomGarant = trim((string) ($data['nomGarant'] ?? ''));
        if ($nomGarant === '') {
            $errors['nomGarant'] = 'Le nom du garant est obligatoire.';
        } elseif (mb_strlen($nomGarant, 'UTF-8') < 2) {
            $errors['nomGarant'] = 'Le nom doit contenir au moins 2 caracteres.';
        } elseif (mb_strlen($nomGarant, 'UTF-8') > 150) {
            $errors['nomGarant'] = 'Le nom du garant ne peut pas depasser 150 caracteres.';
        } elseif (!preg_match('/^[\p{L}\s\-\']+$/u', $nomGarant)) {
            $errors['nomGarant'] = 'Le nom du garant ne doit contenir que des lettres.';
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @return array<int, array<string, mixed>>
     */
    private function decorateGaranties(array $garanties): array
    {
        foreach ($garanties as &$garantie) {
            $rawType = (string) ($garantie['typeGarantie'] ?? '');
            $normalizedType = $this->normalizeGarantieTypeValue($rawType);
            $garantie['typeGarantieRaw'] = $rawType;
            $garantie['typeGarantieValue'] = $normalizedType ?? $rawType;
            $garantie['typeGarantieLabel'] = $this->garantieTypeLabel($rawType);
            $garantie['typeGarantie'] = $garantie['typeGarantieLabel'];

            $status = $this->documentWorkflowService->resolveStatus((string) ($garantie['statutVerificationDocument'] ?? ''));
            $garantie['statutVerificationDocument'] = $status;
            $garantie['statutVerificationDocumentLabel'] = DocumentVerificationStatus::label($status);
            $documentStatus = $this->normalizeDocumentStatus((string) ($garantie['statutDocument'] ?? $this->documentStatusFromVerificationStatus($status)));
            $garantie['statutDocument'] = $documentStatus;
            $garantie['statutDocumentLabel'] = $this->documentStatusLabel($documentStatus);
            $garantie['remarqueAdmin'] = trim((string) ($garantie['remarqueAdmin'] ?? ''));
            $garantie['documentUrl'] = (string) ($garantie['documentUrl'] ?? ($garantie['documentJustificatif'] ?? ''));
            $garantie['documentFeedbackMessage'] = $this->documentWorkflowService->userMessage(
                $status,
                (string) ($garantie['remarqueAdmin'] ?? '')
            );
            $estimated = (float) ($garantie['valeurEstimee'] ?? 0);
            $retained = (float) ($garantie['valeurRetenue'] ?? 0);
            $garantie['coveragePercent'] = $estimated > 0 ? round(min(100, ($retained / $estimated) * 100), 1) : 0.0;
        }
        unset($garantie);

        return $garanties;
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     */
    private function findGarantieById(array $garanties, int $garantieId): ?array
    {
        foreach ($garanties as $garantie) {
            if ((int) ($garantie['idGarantie'] ?? 0) == $garantieId) {
                return $garantie;
            }
        }

        return null;
    }

    private function moveUploadedGuaranteeDocument(UploadedFile $file): string
    {
        $uploadDirectory = dirname(__DIR__, 3).'/public/uploads/garanties';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new FileException('Impossible de créer le répertoire de stockage du document.');
        }

        $filename = bin2hex(random_bytes(8)).'_' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $file->getClientOriginalName());
        $file->move($uploadDirectory, $filename);

        return 'uploads/garanties/'.$filename;
    }

    private function normalizeFraudGuaranteeStatus(string $status): string
    {
        return match ($status) {
            'valide' => 'Validée',
            'à vérifier' => 'À vérifier',
            'suspect' => 'Suspect',
            'rejeté' => 'Rejetée',
            default => 'En attente',
        };
    }

    private function getFraudBadgeClass(string $level, string $status): string
    {
        if ($status === 'rejeté' || $level === 'élevé') {
            return 'text-bg-danger';
        }

        if ($status === 'suspect' || $status === 'à vérifier') {
            return 'text-bg-warning';
        }

        return 'text-bg-success';
    }

    // -------------------------------------------------------------------------
    // Detail
    // -------------------------------------------------------------------------

    /**
     * Builds the detailed view for a single garantie.
     */
    public function buildGarantieDetail(BankingService $bankingService, int $idGarantie): ?array
    {
        $garanties = $bankingService->listGaranties();
        $garantie  = null;
        foreach ($garanties as $g) {
            if ((int) ($g['idGarantie'] ?? 0) === $idGarantie) {
                $garantie = $g;
                break;
            }
        }

        if ($garantie === null) {
            return null;
        }

        $creditIds = array_map('intval', $garantie['linkedCreditIds'] ?? []);
        $credit = null;
        $credits = [];
        if ($creditIds !== []) {
            foreach ($bankingService->listCredits() as $c) {
                if (in_array((int) ($c['idCredit'] ?? 0), $creditIds, true)) {
                    $credits[] = $c;
                }
            }
            $credit = $credits[0] ?? null;
        }

        return [
            'garantie' => $garantie,
            'credits'  => $credits,
            'credit'   => $credit,
        ];
    }

    // -------------------------------------------------------------------------
    // PDF export
    // -------------------------------------------------------------------------

    /**
     * @return array{string, string} [$pdfContent, $filename]
     */
    public function buildGarantiePdf(BankingService $bankingService, ExportService $exportService, ?int $idGarantie): array
    {
        if ($idGarantie !== null) {
            $garantie = null;
            foreach ($bankingService->listGaranties() as $g) {
                if ((int) ($g['idGarantie'] ?? 0) === $idGarantie) {
                    $garantie = $g;
                    break;
                }
            }

            if ($garantie === null) {
                return ['', ''];
            }

            $headers = ['Champ', 'Valeur'];
            $rows    = [
                ['ID Garantie',      (string) ($garantie['idGarantie'] ?? '—')],
                ['ID Crédit',        (string) ($garantie['idCredit'] ?? '—')],
                ['Type',             (string) ($garantie['typeGarantie'] ?? '—')],
                ['Valeur estimée',   number_format((float) ($garantie['valeurEstimee'] ?? 0), 2, '.', ' ').' DT'],
                ['Valeur retenue',   number_format((float) ($garantie['valeurRetenue'] ?? 0), 2, '.', ' ').' DT'],
                ['Date évaluation',  (string) ($garantie['dateEvaluation'] ?? '—')],
                ['Nom garant',       (string) ($garantie['nomGarant'] ?? '—')],
                ['Adresse bien',     (string) ($garantie['adresseBien'] ?? '—')],
                ['Statut',           (string) ($garantie['statut'] ?? '—')],
            ];
            $stats = [
                ['label' => 'Type',          'value' => (string) ($garantie['typeGarantie'] ?? '—')],
                ['label' => 'Val. estimée',  'value' => number_format((float) ($garantie['valeurEstimee'] ?? 0), 2, '.', ' ').' DT'],
                ['label' => 'Statut',        'value' => (string) ($garantie['statut'] ?? '—')],
            ];
            $title    = sprintf('Fiche Garantie — #%d', $idGarantie);
            $subtitle = sprintf('Détail complet de la garantie #%d exportée depuis Nexora.', $idGarantie);
            $filename = sprintf('nexora-garantie-%d.pdf', $idGarantie);
        } else {
            $garanties     = $bankingService->listGaranties();
            $headers       = ['ID', 'ID Crédit', 'Type', 'Val. estimée', 'Val. retenue', 'Date éval.', 'Garant', 'Statut'];
            $rows          = [];
            $totalEstimee  = 0.0;
            $totalRetenue  = 0.0;
            foreach ($garanties as $g) {
                $totalEstimee += (float) ($g['valeurEstimee'] ?? 0);
                $totalRetenue += (float) ($g['valeurRetenue'] ?? 0);
                $rows[] = [
                    (string) ($g['idGarantie'] ?? ''),
                    (string) ($g['idCredit'] ?? ''),
                    (string) ($g['typeGarantie'] ?? ''),
                    number_format((float) ($g['valeurEstimee'] ?? 0), 2, '.', ' '),
                    number_format((float) ($g['valeurRetenue'] ?? 0), 2, '.', ' '),
                    (string) ($g['dateEvaluation'] ?? ''),
                    (string) ($g['nomGarant'] ?? ''),
                    (string) ($g['statut'] ?? ''),
                ];
            }
            $stats = [
                ['label' => 'Total garanties', 'value' => (string) count($garanties)],
                ['label' => 'Val. estimée',    'value' => number_format($totalEstimee, 2, '.', ' ').' DT'],
                ['label' => 'Val. retenue',    'value' => number_format($totalRetenue, 2, '.', ' ').' DT'],
            ];
            $title    = 'Rapport Garanties';
            $subtitle = 'Export complet de toutes les garanties depuis Nexora.';
            $filename = 'nexora-garanties-all.pdf';
        }

        return [
            $exportService->buildPdf($title, $headers, $rows, $stats, $subtitle, '#00bcd4'),
            $filename,
        ];
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    /**
     * Computes per-status statistics for KPI cards.
     *
     * @param array<int, array<string, mixed>> $garanties
     */
    public function buildGarantieStats(array $garanties): array
    {
        $total         = count($garanties);
        $totalEstimee  = 0.0;
        $totalRetenue  = 0.0;
        $amounts       = ['Actif' => 0.0, 'RejetÃ©' => 0.0, 'En attente' => 0.0, 'Autre' => 0.0];
        $counts        = ['Actif' => 0, 'Rejeté' => 0, 'En attente' => 0, 'Autre' => 0];

        foreach ($garanties as $g) {
            $estimated = (float) ($g['valeurEstimee'] ?? 0);

            $totalEstimee += $estimated;
            $totalRetenue += (float) ($g['valeurRetenue'] ?? 0);

            $statut = strtolower(trim((string) ($g['statut'] ?? '')));
            if (str_contains($statut, 'actif') || str_contains($statut, 'valid')) {
                $counts['Actif']++;
                $amounts['Actif'] += $estimated;
            } elseif (str_contains($statut, 'rejet') || str_contains($statut, 'refus')) {
                $counts['Rejeté']++;
                $amounts['RejetÃ©'] += $estimated;
            } elseif (str_contains($statut, 'attente') || str_contains($statut, 'pending')) {
                $counts['En attente']++;
                $amounts['En attente'] += $estimated;
            } else {
                $counts['Autre']++;
                $amounts['Autre'] += $estimated;
            }
        }

        return [
            'total'         => $total,
            'total_estimee' => $totalEstimee,
            'total_retenue' => $totalRetenue,
            'counts'        => $counts,
            'amounts'       => $amounts,
            'colors'        => [
                'Actif'      => '#22c55e',
                'Rejeté'     => '#ef4444',
                'En attente' => '#f97316',
                'Autre'      => '#6b7280',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Portal filtering / sorting
    // -------------------------------------------------------------------------

    /**
     * @return array{q:string, search_in:string, filter:string, sort:string, dir:string}
     */
    private function resolvePortalGarantieQuery(?Request $request): array
    {
        $allowedSearchFields = ['all', 'id', 'credit', 'type', 'estimee', 'retenue', 'date', 'garant', 'statut'];
        $allowedFilters      = ['all', 'actif', 'en_attente', 'rejete', 'autre'];
        $allowedSorts        = ['id', 'credit', 'type', 'estimee', 'retenue', 'date', 'garant', 'statut'];

        $query    = trim((string) ($request?->query->get('q') ?? ''));
        $searchIn = strtolower(trim((string) ($request?->query->get('search_in') ?? 'all')));
        $filter   = strtolower(trim((string) ($request?->query->get('filter') ?? 'all')));
        $sort     = strtolower(trim((string) ($request?->query->get('sort') ?? '')));
        $dir      = strtolower(trim((string) ($request?->query->get('dir') ?? 'asc')));

        if (!in_array($searchIn, $allowedSearchFields, true)) {
            $searchIn = 'all';
        }
        if (!in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = '';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        return ['q' => $query, 'search_in' => $searchIn, 'filter' => $filter, 'sort' => $sort, 'dir' => $dir];
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @param array{q:string, search_in:string, filter:string, sort:string, dir:string} $query
     * @return array<int, array<string, mixed>>
     */
    private function filterAndSortPortalGaranties(array $garanties, array $query): array
    {
        $filtered = array_values(array_filter(
            $garanties,
            fn (array $g): bool => $this->matchesPortalGarantieFilter($g, $query['filter'])
                && $this->matchesPortalGarantieSearch($g, $query['q'], $query['search_in'])
        ));

        if ($query['sort'] === '') {
            return $filtered;
        }

        $direction = $query['dir'] === 'desc' ? -1 : 1;

        usort($filtered, function (array $left, array $right) use ($query, $direction): int {
            $cmp = match ($query['sort']) {
                'id'      => ((int) ($left['idGarantie'] ?? 0)) <=> ((int) ($right['idGarantie'] ?? 0)),
                'credit'  => ((int) ($left['idCredit'] ?? 0)) <=> ((int) ($right['idCredit'] ?? 0)),
                'estimee' => ((float) ($left['valeurEstimee'] ?? 0)) <=> ((float) ($right['valeurEstimee'] ?? 0)),
                'retenue' => ((float) ($left['valeurRetenue'] ?? 0)) <=> ((float) ($right['valeurRetenue'] ?? 0)),
                'date'    => strtotime((string) ($left['dateEvaluation'] ?? '')) <=> strtotime((string) ($right['dateEvaluation'] ?? '')),
                default   => strnatcmp(
                    $this->normalizeGarantieText((string) ($left[$this->garantieSortField($query['sort'])] ?? '')),
                    $this->normalizeGarantieText((string) ($right[$this->garantieSortField($query['sort'])] ?? ''))
                ),
            };

            return $cmp * $direction;
        });

        return $filtered;
    }

    private function garantieSortField(string $sort): string
    {
        return match ($sort) {
            'type'   => 'typeGarantie',
            'garant' => 'nomGarant',
            'statut' => 'statut',
            default  => 'idGarantie',
        };
    }

    private function matchesPortalGarantieFilter(array $garantie, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        $statut = $this->normalizeGarantieText((string) ($garantie['statut'] ?? ''));

        return match ($filter) {
            'actif'      => str_contains($statut, 'actif') || str_contains($statut, 'valid'),
            'en_attente' => str_contains($statut, 'attente') || str_contains($statut, 'pending'),
            'rejete'     => str_contains($statut, 'rejet') || str_contains($statut, 'refus'),
            'autre'      => !str_contains($statut, 'actif') && !str_contains($statut, 'valid')
                         && !str_contains($statut, 'attente') && !str_contains($statut, 'pending')
                         && !str_contains($statut, 'rejet') && !str_contains($statut, 'refus'),
            default => true,
        };
    }

    private function matchesPortalGarantieSearch(array $garantie, string $query, string $searchIn): bool
    {
        $needle = $this->normalizeGarantieText($query);
        if ($needle === '') {
            return true;
        }

        $fields = ['id', 'credit', 'type', 'estimee', 'retenue', 'date', 'garant', 'statut'];

        if ($searchIn === 'all') {
            foreach ($fields as $field) {
                if (str_contains($this->portalGarantieSearchValue($garantie, $field), $needle)) {
                    return true;
                }
            }

            return false;
        }

        return str_contains($this->portalGarantieSearchValue($garantie, $searchIn), $needle);
    }

    private function portalGarantieSearchValue(array $garantie, string $field): string
    {
        $value = match ($field) {
            'id'      => (string) ($garantie['idGarantie'] ?? ''),
            'credit'  => (string) ($garantie['idCredit'] ?? ''),
            'type'    => (string) ($garantie['typeGarantie'] ?? ''),
            'estimee' => number_format((float) ($garantie['valeurEstimee'] ?? 0), 2, '.', ' '),
            'retenue' => number_format((float) ($garantie['valeurRetenue'] ?? 0), 2, '.', ' '),
            'date'    => (string) ($garantie['dateEvaluation'] ?? ''),
            'garant'  => (string) ($garantie['nomGarant'] ?? ''),
            'statut'  => (string) ($garantie['statut'] ?? ''),
            default   => '',
        };

        return $this->normalizeGarantieText($value);
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @return array{all:int, actif:int, en_attente:int, rejete:int, autre:int}
     */
    private function buildPortalGarantieStatusCounts(array $garanties): array
    {
        $counts = ['all' => count($garanties), 'actif' => 0, 'en_attente' => 0, 'rejete' => 0, 'autre' => 0];

        foreach ($garanties as $g) {
            $statut = $this->normalizeGarantieText((string) ($g['statut'] ?? ''));
            if (str_contains($statut, 'actif') || str_contains($statut, 'valid')) {
                $counts['actif']++;
            } elseif (str_contains($statut, 'attente') || str_contains($statut, 'pending')) {
                $counts['en_attente']++;
            } elseif (str_contains($statut, 'rejet') || str_contains($statut, 'refus')) {
                $counts['rejete']++;
            } else {
                $counts['autre']++;
            }
        }

        return $counts;
    }

    private function normalizeGarantieText(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @return array<int, array<string, mixed>>
     */
    private function enrichGarantiesWithFraudData(array $garanties, int $userId): array
    {
        if ($userId <= 0 || $garanties === []) {
            return $garanties;
        }

        $garantieIds = [];
        foreach ($garanties as $garantie) {
            $garantieId = (int) ($garantie['idGarantie'] ?? 0);
            if ($garantieId > 0) {
                $garantieIds[] = $garantieId;
            }
        }

        $historyByGarantie = $this->fraudDetectionService->loadGuaranteeFraudHistory($userId, array_values(array_unique($garantieIds)));

        foreach ($garanties as &$garantie) {
            $garantieId = (int) ($garantie['idGarantie'] ?? 0);
            $history = array_slice($historyByGarantie[$garantieId] ?? [], 0, 5);

            $garantie['fraud_analysis'] = $history[0] ?? null;
            $garantie['fraud'] = $history[0] ?? null;
            $garantie['fraud_history'] = $history;
            $garantie['fraud_history_count'] = count($historyByGarantie[$garantieId] ?? []);
            $garantie['ocr_payload'] = $this->fraudDetectionService->prepareExternalOcrPayload($garantie);
        }
        unset($garantie);

        return $garanties;
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @return array<string, int>
     */
    private function buildFraudStats(array $garanties): array
    {
        $stats = [
            'analysed' => 0,
            'valide' => 0,
            'a_verifier' => 0,
            'suspect' => 0,
            'rejete' => 0,
        ];

        foreach ($garanties as $garantie) {
            $analysis = is_array($garantie['fraud_analysis'] ?? null) ? $garantie['fraud_analysis'] : null;
            if ($analysis === null) {
                continue;
            }

            ++$stats['analysed'];
            $status = strtolower(trim((string) ($analysis['status_key'] ?? $analysis['status'] ?? '')));

            if (in_array($status, ['a verifier', 'a_verifier'], true)) {
                ++$stats['a_verifier'];
            } elseif ($status === 'suspect') {
                ++$stats['suspect'];
            } elseif ($status === 'rejete') {
                ++$stats['rejete'];
            } else {
                ++$stats['valide'];
            }
        }

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $garanties
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function extractFraudHistoryMap(array $garanties): array
    {
        $history = [];

        foreach ($garanties as $garantie) {
            $garantieId = (int) ($garantie['idGarantie'] ?? 0);
            if ($garantieId <= 0) {
                continue;
            }

            $history[$garantieId] = is_array($garantie['fraud_history'] ?? null)
                ? $garantie['fraud_history']
                : [];
        }

        return $history;
    }

    private function resolveFraudFlashType(string $status): string
    {
        return match (strtolower(trim($status))) {
            'a verifier', 'a_verifier' => 'warning',
            'suspect', 'rejete' => 'error',
            default => 'success',
        };
    }

    // -------------------------------------------------------------------------
    // Form helpers (session persistence)
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    public function getFormErrors(Request $request, string $formKey): array
    {
        return (array) $request->getSession()->get('nexora.form_errors.'.$formKey, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormData(Request $request, string $formKey): array
    {
        return (array) $request->getSession()->get('nexora.form_data.'.$formKey, []);
    }

    // -------------------------------------------------------------------------
    // History
    // -------------------------------------------------------------------------

    private function logHistory(Request $request, string $action, array $data): void
    {
        $session = $request->getSession();
        /** @var array<int, array<string, mixed>> $history */
        $history = $session->get('nexora.garanties_history', []);

        $labels = [
            'garantie_add'    => 'Ajouter garantie',
            'garantie_edit'   => 'Modifier garantie',
            'garantie_delete' => 'Supprimer garantie',
        ];

        $resolvedAction = $action;
        if ($action === 'garantie_save') {
            $resolvedAction = ($data['idGarantie'] ?? '') !== '' ? 'garantie_edit' : 'garantie_add';
        }

        $idGarantie = (string) ($data['idGarantie'] ?? '—');
        $idCredit   = (string) ($data['idCredit'] ?? '—');
        $type       = $this->garantieTypeLabel((string) ($data['typeGarantie'] ?? ''));
        $estimee    = isset($data['valeurEstimee']) ? number_format((float) $data['valeurEstimee'], 2, '.', ' ').' DT' : '—';
        $statut     = (string) ($data['statut'] ?? '');

        $detail = $type !== '' ? $type : ($idGarantie !== '—' ? '#'.$idGarantie : '—');
        if ($idCredit !== '—') {
            $detail .= ' · Crédit #'.$idCredit;
        }
        if ($statut !== '') {
            $detail .= ' · '.$statut;
        }
        if ($estimee !== '—') {
            $detail .= ' · Estimée '.$estimee;
        }

        array_unshift($history, [
            'action'     => $labels[$resolvedAction] ?? $resolvedAction,
            'detail'     => $detail,
            'idGarantie' => $idGarantie,
            'idCredit'   => $idCredit,
            'timestamp'  => date('d/m/Y H:i:s'),
            'type'       => str_contains($resolvedAction, 'delete') ? 'delete' : 'save',
        ]);

        $session->set('nexora.garanties_history', array_slice($history, 0, 50));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(Request $request): array
    {
        return (array) $request->getSession()->get('nexora.garanties_history', []);
    }

    private function garantieTypeLabel(string $value): string
    {
        return Garantiecredit::typeLabel($value);
    }

    private function normalizeGarantieTypeValue(string $value): ?string
    {
        return Garantiecredit::normalizeTypeValue($value);
    }

    private function normalizeDocumentStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'valide' => 'valide',
            'refuse', 'rejete', 'rejetee', 'rejeté', 'rejetée' => 'refuse',
            default => 'en_attente',
        };
    }

    private function documentStatusFromVerificationStatus(string $verificationStatus): string
    {
        $normalized = strtolower(trim($verificationStatus));

        return match ($normalized) {
            DocumentVerificationStatus::VALIDE => 'valide',
            DocumentVerificationStatus::REJETE => 'refuse',
            default => 'en_attente',
        };
    }

    private function documentStatusLabel(string $status): string
    {
        return match ($this->normalizeDocumentStatus($status)) {
            'valide' => 'Valide',
            'refuse' => 'Refuse',
            default => 'En attente',
        };
    }

    /**
     * @param array<string, mixed> $garantie
     */
    private function notifyGarantieOwnerByMailAndSms(BankingService $bankingService, array $garantie, string $message): void
    {
        $ownerId = (int) ($garantie['resolved_user_id'] ?? ($garantie['idUser'] ?? 0));
        if ($ownerId <= 0) {
            return;
        }

        try {
            $title = 'Mise a jour de votre garantie';
            $this->notificationService->createNotification(
                $ownerId,
                null,
                $ownerId,
                'GARANTIE_UPDATE',
                $title,
                $message,
                true
            );

            $ownerPhone = '';
            foreach ($bankingService->listUsers() as $user) {
                if ((int) ($user['idUser'] ?? 0) === $ownerId) {
                    $ownerPhone = trim((string) ($user['telephone'] ?? ''));
                    break;
                }
            }

            if ($ownerPhone !== '') {
                $this->notificationService->sendSms($ownerPhone, $message);
            }
        } catch (\Throwable) {
            // Ne pas bloquer le workflow admin en cas d'echec mail/SMS.
        }
    }

    // -------------------------------------------------------------------------
    // Shared helper
    // -------------------------------------------------------------------------

    private function requestInt(Request $request, string $key): ?int
    {
        $value = $request->request->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }
}
