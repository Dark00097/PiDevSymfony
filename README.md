# Nexora Bank

Nexora Bank est un projet bancaire multi-plateforme compose de trois parties :

- une application web Symfony pour le portail client, l'administration et les API ;
- une application desktop JavaFX pour la gestion bancaire locale ;
- une application mobile React Native connectee au backend Symfony.

Le projet couvre les principaux parcours d'une banque numerique : authentification, gestion des utilisateurs, comptes bancaires, transactions, credits, garanties, reclamations, notifications, cashback, tableaux de bord, exports PDF, paiements Stripe, QR login, support client et assistants IA.

## Structure du projet

```text
pidev/
+-- java/
|   +-- JavaFinal Version/          # Application desktop JavaFX / Maven
+-- symfony/
    +-- Symfony Final Version/      # Backend web Symfony + app mobile React Native
        +-- NexoraMobile/           # Application mobile React Native CLI
```

## Technologies utilisees

### Backend web

- PHP 8.2+
- Symfony 7.4
- Doctrine ORM et migrations
- Twig
- MySQL
- PHPUnit
- Stripe PHP
- Twilio
- Cloudinary
- Gemini / Groq / OpenRouter pour les fonctionnalites IA
- Dompdf, Knp Snappy et QR Code

### Application desktop

- Java 17
- JavaFX 17
- Maven
- MySQL Connector/J
- Stripe Java
- Jakarta Mail
- PDFBox / iText
- ZXing pour les QR codes
- Services Twilio, IA, traduction et notifications

### Application mobile

- React Native CLI 0.74
- React 18
- TypeScript
- Async Storage
- Camera Kit pour le scan QR
- Jest / ESLint

## Fonctionnalites principales

- Authentification, inscription, mot de passe oublie et deconnexion.
- Gestion des profils client et administrateur.
- Gestion des utilisateurs, roles, statuts et securite.
- Comptes bancaires : creation, consultation, plafonds, soldes et exports.
- Transactions : virements, paiements, historique, controle des plafonds et Stripe.
- Credits : demandes, garanties, scoring, simulation et analyse IA.
- Coffres virtuels et strategies intelligentes de transfert automatique.
- Cashback, partenaires, roue de fortune et gamification.
- Reclamations, support client temps reel et reponses assistees par IA.
- Notifications email, SMS et interface.
- Exports PDF et generation de QR codes.
- API mobile pour login, profil, notifications, support et QR login.

## Prerequis

Installez les outils suivants avant de lancer le projet :

- PHP 8.2 ou plus recent
- Composer
- MySQL
- Symfony CLI, optionnel mais recommande
- Java JDK 17
- Maven
- Node.js 18 ou plus recent
- Android Studio pour lancer l'application mobile Android
- Xcode et CocoaPods pour iOS, uniquement sur macOS

## Configuration de la base de donnees

Le projet utilise une base MySQL nommee par defaut `projetpidev`.

Fichiers SQL utiles :

- `symfony/Symfony Final Version/projetpidev.sql`
- `java/JavaFinal Version/projetpidev (2).sql`
- `symfony/Symfony Final Version/migrations/`
- scripts SQL additionnels dans les dossiers Symfony et Java pour les corrections ou fonctionnalites specifiques.

Exemple de creation locale :

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS projetpidev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p projetpidev < "symfony/Symfony Final Version/projetpidev.sql"
```

Adaptez ensuite la variable `DATABASE_URL` dans `symfony/Symfony Final Version/.env`.

L'application Java utilise actuellement une connexion directe dans :

```text
java/JavaFinal Version/src/main/java/com/nexora/bank/Utils/MyDB.java
```

Par defaut, elle cible :

```text
jdbc:mysql://localhost:3306/projetpidev
user: root
password: vide
```

## Lancer le backend Symfony

Depuis le dossier Symfony :

```bash
cd "symfony/Symfony Final Version"
composer install
php bin/console doctrine:migrations:migrate
symfony server:start --port=8001
```

Sans Symfony CLI :

```bash
php -S 127.0.0.1:8001 -t public
```

Routes principales :

- `http://127.0.0.1:8001/` : page d'accueil
- `http://127.0.0.1:8001/login` : connexion
- `http://127.0.0.1:8001/signup` : inscription
- `http://127.0.0.1:8001/portal` : portail client
- `http://127.0.0.1:8001/admin` : espace administrateur
- `http://127.0.0.1:8001/api/mobile/*` : API mobile

## Lancer l'application JavaFX

Depuis le dossier Java :

```bash
cd "java/JavaFinal Version"
mvn clean install
mvn javafx:run
```

Point d'entree :

```text
com.nexora.bank.MainApp
```

La premiere vue chargee est :

```text
src/main/resources/fxml/Home.fxml
```

## Lancer l'application mobile

Depuis le dossier mobile :

```bash
cd "symfony/Symfony Final Version/NexoraMobile"
npm install
npm start
```

Android :

```bash
npm run android
```

iOS :

```bash
cd ios
pod install
cd ..
npm run ios
```

URL backend a utiliser dans l'application mobile :

- Android emulator : `http://10.0.2.2:8001`
- iOS simulator : `http://127.0.0.1:8001`
- telephone physique : l'adresse IP locale du PC, par exemple `http://192.168.1.50:8001`

## Variables d'environnement importantes

Le backend Symfony s'appuie sur plusieurs variables dans `.env` :

- `APP_ENV`, `APP_SECRET`, `DEFAULT_URI`
- `DATABASE_URL`
- `MAILER_DSN`
- `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`
- `GEMINI_API_KEY`, `GROQ_API_KEY`, `OPENROUTER_API_KEY`
- `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_SERVICE_SID`, `TWILIO_FROM_NUMBER`
- `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`
- `WKHTMLTOPDF_PATH`, `WKHTMLTOIMAGE_PATH`

Cote Java, des cles IA sont lues depuis `.env` et la configuration Twilio se trouve dans `config/twilio.properties`.

Important : les cles secretes ne doivent pas etre versionnees dans un depot public. Utilisez un fichier local non commite ou des variables d'environnement de la machine.

## Tests

### Symfony

```bash
cd "symfony/Symfony Final Version"
php bin/phpunit
```

Tests presents :

- `CashbackManagerTest`
- `CompteManagerTest`
- `CreditManagerTest`
- `TransactionLimitsTest`
- `TransactionManagerTest`
- `UserManagerTest`

### Mobile

```bash
cd "symfony/Symfony Final Version/NexoraMobile"
npm test
npm run lint
```

### Java

Le projet est structure avec Maven. Pour verifier la compilation :

```bash
cd "java/JavaFinal Version"
mvn test
mvn clean package
```

## Documentation incluse

Le projet contient deja plusieurs documents techniques utiles, notamment :

- `README_TRANSFERTS_AUTOMATIQUES.md`
- `README_PLAFONDS.md`
- `README_TESTS_UNITAIRES.md`
- `README_CASHBACK_TESTS.md`
- `GUIDE_PAIEMENT_STRIPE.md`
- `GUIDE_UTILISATION.md`
- `GUIDE_TRADUCTION_MULTILINGUE.md`
- `LANCEMENT_APPLICATION.md`
- `ARCHITECTURE_DIAGRAM.txt`

Ces fichiers donnent des details sur certaines fonctionnalites avancees comme les plafonds de transaction, les transferts automatiques, le cashback, Stripe, les formulaires et les tests.

## Notes de maintenance

- Les dossiers `vendor/`, `node_modules/`, `target/`, `var/cache/` et les fichiers de build peuvent etre regeneres.
- Les chemins contiennent des espaces, donc gardez les guillemets dans les commandes `cd`.
- Verifiez que MySQL est lance avant de demarrer Symfony ou JavaFX.
- Lancez Symfony sur le port `8001` si vous utilisez l'application mobile avec sa configuration actuelle.
- Pour un environnement propre, deplacez les identifiants sensibles hors des fichiers suivis par Git.

## Resume

Nexora Bank est une solution bancaire complete combinant un portail web Symfony, une application desktop JavaFX et une application mobile React Native. Les trois parties partagent le meme domaine fonctionnel : comptes, transactions, credits, securite, support, notifications, paiements et services intelligents.
