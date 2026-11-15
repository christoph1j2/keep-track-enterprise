#### Adresářová struktura

```text
keep-track-enterprise/
├── Dockerfile                    # PHP 8.2 + Apache image, instalace Composeru a závislostí, upload limity a práva ke storage
├── docker-compose.yaml           # Definice služeb web (:8080), db (MariaDB + auto-seed) a phpmyadmin (:8081)
├── composer.json / composer.lock # Definice závislostí (twig/twig) a PSR-4 autoloadingu pro Src\
├── dev.env                       # Výchozí proměnné prostředí (porty, DB připojení)
├── docs/                         # Dokumentace k projektu
├── public/                       # Veřejný document root Apache
│   ├── index.php                 # Front Controller – konfigurace session a registrace všech rout
│   └── assets/                   # Statické soubory frontendu (css/header.css, css/table.css, img/, js/)
├── sql/                          # Databázové skripty (seed.sql – DDL schéma 9 tabulek a testovací data)
├── storage/                      # Privátní úložiště aplikace mimo webroot (receipts/ pro nahrané účtenky)
├── vendor/                       # Knihovny nainstalované Composerem (vytváří se při buildu / composer install)
└── src/                          # Zdrojový kód aplikace (PSR-4 namespace Src\)
    ├── Core/                     # Jádro aplikace (Database.php – PDO konektor, Router.php – směrování požadavků)
    ├── Middleware/               # Bezpečnostní vrstva (Auth.php – kontrola přihlášení/rolí, CSRF.php – CSRF ochrana)
    ├── Validators/               # Validátory vstupních formulářů (BaseValidator.php, RegisterValidator.php)
    ├── Models/                   # Databázové modely (BaseModel, UserModel, ClientModel, InvoiceModel, PaymentModel, ExpenseModel)
    ├── Controllers/              # Základní a autentizační kontrolery (BaseController, HomeController, AuthController, UserController, ErrorController)
    │   └── Dashboard/            # Kontrolery přihlášené části (OsvcController, DashboardController, Client-, Invoice-, Payment-, Expense-, Accountant-, AdminController)
    └── Views/                    # Twig šablony (layout.twig + složky Partial/, Home/, User/, Dashboard/, Client/, Invoice/, Payment/, Expense/, Accountant/, Admin/, Errors/)
```