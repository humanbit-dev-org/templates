# Sistema Ruoli Frontend 🌐

## 🎯 Panoramica

Il sistema ruoli frontend gestisce l'accesso degli utenti web alle risorse dell'API. Utilizza un middleware personalizzato con sintassi chiara e supporto per logica OR.

## 📋 Ruoli Disponibili

### 1. **Public** (ID: 1)
- **Descrizione**: Utenti anonimi o pubblici
- **Scopo**: Accesso base alle risorse pubbliche
- **Permessi tipici**: Solo lettura di contenuti pubblici

### 2. **User** (ID: 2)
- **Descrizione**: Utenti registrati standard
- **Scopo**: Accesso alle funzionalità principali del sito
- **Permessi tipici**: Lettura e modifica del proprio profilo, partecipazione ai gruppi

### 3. **Ecommerce** (ID: 3)
- **Descrizione**: Utenti con accesso alle funzionalità e-commerce
- **Scopo**: Gestione ordini, vendite e funzionalità commerciali
- **Permessi tipici**: Creazione/gestione prodotti, elaborazione ordini

## ⚠️ IMPORTANTE: Ruoli vs Modelli

### 🎯 **Concetto Chiave**
**NON confondere RUOLI con MODELLI!**

- **Ruolo** = CHI sei (User, Ecommerce, Public)
- **Modello** = COSA proteggi (User, Order, Article, etc.)

### ❌ **Errore Comune**
```php
// SBAGLIATO - questi sono ruoli, non modelli!
FrontendModelPermission::read(['User', 'Admin']) // ❌
```

### ✅ **Sintassi Corretta**
```php
// CORRETTO - questi sono modelli da proteggere!
FrontendModelPermission::read(['User', 'Order']) // ✅
```

### 🔍 **Come Funziona**
```
👤 Utente con ruolo "User" (role_id=2)
    ↓
🛡️ Middleware: read(['User', 'Ecommerce'])
    ↓  
🔍 Check: "L'utente con ruolo 'User' può leggere il modello 'User'?" → ✅ SÌ
    ↓
✅ Accesso concesso!
```

## 🔧 Utilizzo del Middleware

### Sintassi Nuova (Raccomandata)

```php
use App\Http\Middleware\FrontendModelPermission;

// Singolo modello
Route::middleware([FrontendModelPermission::read('User')])->group(function () {
    // Routes che richiedono permesso di lettura su User
});

// Multipli modelli (OR logic)
Route::middleware([FrontendModelPermission::read(['User', 'Ecommerce'])])->group(function () {
    // Routes accessibili se utente ha permesso User OR Ecommerce
});

// Diversi tipi di permessi
Route::middleware([FrontendModelPermission::create(['Order'])])->group(function () {
    // Routes per creare ordini
});
```

### Metodi Disponibili

```php
// Permessi di lettura
FrontendModelPermission::read($models)

// Permessi di creazione
FrontendModelPermission::create($models)

// Permessi di modifica
FrontendModelPermission::update($models)

// Permessi di eliminazione
FrontendModelPermission::delete($models)

// Metodo generico
FrontendModelPermission::require($models, $action)
```

### Esempi Pratici

```php
// Solo utenti con ruolo User possono accedere
Route::middleware([FrontendModelPermission::read('User')])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
});

// Utenti con ruolo User O Ecommerce possono accedere
Route::middleware([FrontendModelPermission::read(['User', 'Ecommerce'])])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});

// Solo utenti Ecommerce possono creare prodotti
Route::middleware([FrontendModelPermission::create('Product')])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
});
```

## 🏗️ Architettura del Sistema

### 1. **Tabelle Database**
- `roles`: Definizione dei ruoli frontend
- `model_permissions`: Permessi per ruolo e modello
- `users`: Utenti con `role_id` riferimento al ruolo

### 2. **Middleware Flow**
```
Request → Auth Check → Role Check → Model Permission Check → Allow/Deny
```

### 3. **Gate System**
- **Gate**: `frontend-access-model`
- **Provider**: `FrontendAuthServiceProvider`
- **Logica**: Controllo su `role_id` e `model_permissions`

## 🔑 Configurazione Permessi

I permessi sono configurati nella tabella `model_permissions`:

```php
// Esempio: Utente può leggere/modificare User e Article
ModelPermission::create([
    'role_id' => 2, // User role
    'model_name' => ['User', 'Article'],
    'can_read' => true,
    'can_update' => true,
    'can_create' => false,
    'can_delete' => false,
]);
```

## 🚀 Modelli Disponibili

**I modelli sono le classi in `app/Models/` che rappresentano le risorse da proteggere.**

### 🔍 **Come Trovare i Modelli**
```bash
# Lista tutti i modelli disponibili
ls app/Models/
```

### **Principali**
- `User`: Gestione utenti e profili
- `Group`: Gruppi di utenti  
- `Order`: Ordini e acquisti
- `Ecommerce`: Funzionalità e-commerce
- `Article`: Articoli e contenuti
- `Media`: File e allegati

### **Supporto**
- `Contact`: Contatti
- `Notification`: Notifiche
- `Invite`: Inviti di gruppo
- `Category`: Categorie
- `Metadata`: Metadati SEO

### **Sistema** (Critici)
- `Role`: Ruoli frontend
- `BackpackRole`: Ruoli backend
- `ModelPermission`: Permessi

### 💡 **Esempi d'Uso per Modello**

```php
// Proteggere profili utente
FrontendModelPermission::read('User')

// Proteggere creazione ordini  
FrontendModelPermission::create('Order')

// Proteggere gestione gruppi
FrontendModelPermission::update('Group')

// Proteggere accesso e-commerce
FrontendModelPermission::read('Ecommerce')

// Proteggere gestione articoli
FrontendModelPermission::create(['Article', 'Media'])
```

## 🔒 Controlli di Sicurezza

### 1. **Autenticazione Richiesta**
```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Tutte le routes protette richiedono autenticazione
});
```

### 2. **Logica OR**
- Se specifichi `['User', 'Ecommerce']`, basta **uno** dei due permessi
- Ideal per accessi flessibili

### 3. **Fallback Sicuro**
- Nessun permesso = accesso negato
- Utente senza ruolo = accesso negato

## 🛠️ Debugging e Troubleshooting

### Verificare Permessi Utente

```php
// In tinker o controller
$user = User::find(1);
echo "Role ID: " . $user->role_id;

// Verificare permessi diretti
use Illuminate\Support\Facades\Gate;
$canRead = Gate::forUser($user)->allows('frontend-access-model', ['User', 'read']);
echo $canRead ? 'YES' : 'NO';
```

### Log di Debug

Il middleware logga automaticamente:
- Modelli richiesti
- Azione richiesta
- Risultato del controllo

```bash
# Visualizzare log
tail -f storage/logs/laravel.log | grep FrontendModelPermission
```

## 🔄 Migrazione da Sintassi Vecchia

```php
// ❌ Vecchia sintassi (confusa)
Route::middleware(['frontend.permission:User,Ecommerce,read'])

// ✅ Nuova sintassi (chiara)
Route::middleware([FrontendModelPermission::read(['User', 'Ecommerce'])])
```

## ❓ FAQ - Domande Frequenti

### **Q: Come faccio a sapere quale modello usare?**
**A:** I modelli sono le classi in `app/Models/`. Se stai proteggendo l'accesso agli ordini, usa `Order`. Se proteggi i profili utente, usa `User`.

### **Q: Perché uso `User` nel middleware se l'utente ha già ruolo "User"?**
**A:** `User` nel middleware è il **modello** da proteggere, non il ruolo! Significa "controlla se questo utente può accedere alla risorsa User".

### **Q: Posso usare più modelli insieme?**  
**A:** Sì! `FrontendModelPermission::read(['User', 'Order'])` significa accesso se l'utente può leggere User **OR** Order.

### **Q: Come faccio AND logic invece di OR?**
**A:** Usa middleware multipli:
```php
Route::middleware([
    FrontendModelPermission::read('User'),
    FrontendModelPermission::read('Order')
])->group(function () {
    // Accesso solo se può User AND Order
});
```

### **Q: Cosa succede se l'utente non ha permessi?**
**A:** Riceve `403 Forbidden` automaticamente.

### **Q: Come verifico se un utente ha un permesso specifico?**
**A:** 
```php
use Illuminate\Support\Facades\Gate;
$canRead = Gate::allows('frontend-access-model', ['User', 'read']);
```

## 📝 Best Practices

1. **Usa sempre la nuova sintassi** per chiarezza
2. **Raggruppa routes simili** con gli stessi permessi
3. **Testa sempre** i permessi dopo modifiche
4. **Documenta** i permessi richiesti per ogni route
5. **Usa OR logic** per flessibilità, AND logic per sicurezza
6. **Usa nomi modelli esistenti** - controlla sempre `app/Models/`

## 🎯 Esempio Completo - Caso Reale

### **Scenario: Sistema di Gestione Ordini**

```php
use App\Http\Middleware\FrontendModelPermission;

Route::middleware(['auth:sanctum'])->group(function () {
    
    // 👤 Area profilo - solo chi può leggere User
    Route::middleware([FrontendModelPermission::read('User')])->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
    });
    
    // 🛒 Area ordini - flessibile: User OR Ecommerce
    Route::middleware([FrontendModelPermission::read(['User', 'Ecommerce'])])->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });
    
    // 💰 Creazione ordini - solo chi può creare Order
    Route::middleware([FrontendModelPermission::create('Order')])->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
    });
    
    // 🏪 Gestione e-commerce - solo Ecommerce
    Route::middleware([FrontendModelPermission::read('Ecommerce')])->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
    });
    
    // 🔒 Super sicuro - AND logic (due middleware)
    Route::middleware([
        FrontendModelPermission::read('User'),
        FrontendModelPermission::read('Ecommerce')
    ])->group(function () {
        Route::get('/admin-dashboard', [AdminController::class, 'dashboard']);
        // Accesso solo se ha ENTRAMBI i permessi
    });
});
```

### **Chi Può Accedere a Cosa?**

| Route | User (role_id=2) | Ecommerce (role_id=3) | Public (role_id=1) |
|-------|------------------|------------------------|---------------------|
| `/profile` | ✅ | ❌ | ❌ |
| `/orders` | ✅ | ✅ | ❌ |
| `POST /orders` | ✅ | ❌ | ❌ |
| `/products` | ❌ | ✅ | ❌ |
| `/admin-dashboard` | ❌ | ❌ | ❌ |

## 🎉 Vantaggi del Sistema

- ✅ **Sintassi chiara**: Facile da leggere e manutenere
- ✅ **Flessibilità OR**: Accesso multi-ruolo semplice
- ✅ **Type Safety**: Helper con autocompletamento IDE
- ✅ **Debug facile**: Log dettagliati e tool di testing
- ✅ **Performance**: Gate system efficiente
- ✅ **Sicurezza**: Fallback sicuro e controlli robusti
- ✅ **Confusione zero**: Distinzione chiara ruoli vs modelli

---

**Sistema robusto e developer-friendly! 🚀** 