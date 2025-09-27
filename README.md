# Brick - PHP OOP MVC Framework

Ein modulares PHP MVC Framework, das Schritt für Schritt mit klarer Struktur und Git-Workflow entwickelt wird.

## 📋 Projektübersicht

Brick ist ein PHP OOP MVC Projekt mit sauberer Architektur, das folgende Komponenten umfasst:
- **Core Layer**: Request/Response Handling, Routing
- **MVC Pattern**: Controller, Views und Models
- **Repository Pattern**: Datenbankabstraktion
- **Service Layer**: Business Logic
- **Middleware**: Authentication und Request-Verarbeitung
- **Storage System**: Logs, Cache und Sessions

## 🏗️ Projektstruktur

```
brick/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Views/
│   │   └── layout.php
│   ├── Repositories/
│   ├── Services/
│   └── Middleware/
├── core/
│   ├── Request.php
│   ├── Response.php
│   ├── Router.php
│   ├── Controller.php
│   ├── View.php
│   ├── Model.php
│   └── DBConnection.php
├── config/
│   ├── app.php
│   ├── routes.php
│   └── database.php
├── storage/
│   ├── logs/
│   ├── cache/
│   └── sessions/
├── tests/
│   ├── Controllers/
│   ├── Models/
│   └── Services/
├── public/
│   └── index.php
└── composer.json
```

## 🚀 Entwicklungsplan

### Schritt 1: Bootstrap
- ✅ Front Controller (`public/index.php`)
- ✅ Composer Autoload Setup
- ✅ Basis-Konfiguration
- ✅ Grundordner-Struktur

### Schritt 2: Core Layer
- ✅ Request/Response Handling
- ✅ Router System
- ✅ Route-Konfiguration
- ✅ Erste Testroute

### Schritt 3: Controller Base
- ✅ BaseController Implementation
- ✅ HomeController
- ✅ Route-Controller Mapping

### Schritt 4: View System
- ✅ BaseView System
- ✅ Template-Layout
- ✅ View-Rendering in Controller

### Schritt 5: Database & Model
- ✅ Datenbankverbindung
- ✅ BaseModel mit ORM-Features
- ✅ User Model
- ✅ Database-Konfiguration

### Schritt 6: Repositories & Services
- ✅ Repository Pattern
- ✅ UserRepository
- ✅ AuthService
- ✅ Service Layer Architektur

### Schritt 7: Middleware
- ✅ AuthMiddleware
- ✅ Router-Integration
- ✅ Request-Pipeline

### Schritt 8: Storage & Logs
- ✅ Storage-Ordner
- ✅ Logging System
- ✅ Cache & Sessions

### Schritt 9: Tests
- ✅ Test-Struktur
- ✅ Unit Tests
- ✅ PHPUnit/Pest Integration

## 🛠️ Installation & Setup

### Voraussetzungen
- PHP 8.0+
- Composer
- MySQL/PostgreSQL
- Web Server (Apache/Nginx)

### Quick Start

1. **Repository klonen**
```bash
git clone <repository-url> brick
cd brick
```

2. **Dependencies installieren**
```bash
composer install
```

3. **Konfiguration**
```bash
cp config/app.php.example config/app.php
cp config/database.php.example config/database.php
# Konfiguration anpassen
```

4. **Web Server starten**
```bash
php -S localhost:8000 -t public/
```

## 🔧 Git-Workflow

### Development Branch
```bash
git checkout -b dev
# Entwicklung in dev branch
```

### Commit-Struktur
Jeder Entwicklungsschritt folgt einem klaren Commit-Muster:

```bash
# Beispiel Schritt 1
git add composer.json public/index.php config/app.php
git commit -m "Bootstrap project structure, index.php and composer autoload"

# Beispiel Schritt 2
git add core/Request.php core/Response.php core/Router.php config/routes.php
git commit -m "Add core Request, Response, Router and routes configuration"
```

### Release Process
```bash
git checkout main
git merge dev
git tag -a v1.0 -m "First stable release of Brick"
```

## 📖 Usage Examples

### Route Definition
```php
// config/routes.php
return [
    '/' => 'HomeController@index',
    '/user/{id}' => 'UserController@show',
];
```

### Controller
```php
// app/Controllers/HomeController.php
class HomeController extends Controller
{
    public function index()
    {
        return $this->view('home.index', ['title' => 'Welcome']);
    }
}
```

### Model Usage
```php
// app/Models/User.php
$user = User::find(1);
$users = User::all();
```

## 🧪 Testing

```bash
# Unit Tests ausführen
./vendor/bin/phpunit

# Oder mit Pest
./vendor/bin/pest
```

## 📚 Architektur-Prinzipien

- **MVC Pattern**: Klare Trennung von Logic, Data und Presentation
- **Repository Pattern**: Datenbankabstraktion
- **Service Layer**: Business Logic Kapselung
- **Dependency Injection**: Lose Kopplung
- **PSR Standards**: Code-Konventionen

## 🤝 Contributing

1. Fork das Repository
2. Erstelle einen Feature Branch (`git checkout -b feature/amazing-feature`)
3. Committe deine Änderungen (`git commit -m 'Add amazing feature'`)
4. Push zum Branch (`git push origin feature/amazing-feature`)
5. Öffne einen Pull Request

## 📄 License

Dieses Projekt steht unter der [MIT License](LICENSE).

## 📞 Support

Bei Fragen oder Problemen erstelle ein Issue im Repository oder kontaktiere das Entwicklerteam.

## 👨‍💻 Entwickler

**JP Behrens** - [bitka.de](https://bitka.de)

---

**Entwickelt mit ❤️ und klarer Architektur**