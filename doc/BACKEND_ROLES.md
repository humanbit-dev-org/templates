# Sistema Ruoli Backend (Backpack) 🎛️

## 🎯 Panoramica

Il sistema ruoli backend gestisce l'accesso degli amministratori al pannello di controllo Backpack. Utilizza un sistema di permessi granulari per controllare l'accesso a modelli e operazioni CRUD.

## 📋 Ruoli Disponibili

### 1. **Admin** (ID: 1)
- **Descrizione**: Amministratore sistema
- **Scopo**: Gestione completa del sistema, esclusi ruoli critici
- **Permessi tipici**: CRUD su tutti i modelli eccetto gestione ruoli
- **Restrizioni**: Non può modificare ruoli o permessi critici

### 2. **Developer** (ID: 2)
- **Descrizione**: Sviluppatore con accesso completo
- **Scopo**: Accesso totale per sviluppo e manutenzione
- **Permessi tipici**: CRUD su TUTTI i modelli inclusi ruoli
- **Restrizioni**: Nessuna

### 3. **Guest** (ID: 3)
- **Descrizione**: Ospite con accesso limitato
- **Scopo**: Visualizzazione dati senza modifiche
- **Permessi tipici**: Solo lettura sulla maggior parte dei modelli
- **Restrizioni**: Nessuna creazione/modifica/eliminazione

### 4. **Author** (ID: 4)
- **Descrizione**: Autore di contenuti
- **Scopo**: Gestione contenuti editoriali
- **Permessi tipici**: CRUD su Article, Media, Attachment
- **Restrizioni**: Non può gestire utenti o configurazioni

## 🔧 Utilizzo del Middleware

### Sintassi Middleware

```php
// Nei controller Backpack
use App\Http\Traits\ChecksBackpackPermissions;

class YourCrudController extends CrudController
{
    use ChecksBackpackPermissions;
    
    public function setup()
    {
        // Applica controlli automatici basati sul nome del controller
        $this->setupPermissionChecks();
    }
}
```

### Controlli Manuali

```php
// In controller o middleware
use App\Http\Traits\ChecksBackpackPermissions;

// Verificare se utente può accedere a un modello
if ($this->userCan('User', 'read')) {
    // Permesso concesso
}

// Verificare accesso menu (statico)
if (ChecksBackpackPermissions::userCanAccessMenu('User')) {
    // Mostra menu item
}
```

## 🏗️ Architettura del Sistema

### 1. **Tabelle Database**
- `backpack_roles`: Definizione dei ruoli backend
- `model_permissions`: Permessi per ruolo e modello
- `users`: Utenti con `backpack_role_id` riferimento al ruolo

### 2. **Middleware Flow**
```
Request → Backpack Auth → Role Check → Model Permission Check → CRUD Allow/Deny
```

### 3. **Gate System**
- **Gate**: `backpack-access-model`
- **Provider**: `BackpackAuthServiceProvider`
- **Logica**: Controllo su `backpack_role_id` e `model_permissions`

## 🔑 Configurazione Permessi

### Struttura Permessi

```php
// Esempio: Admin può tutto tranne gestione ruoli
ModelPermission::create([
    'backpack_role_id' => 1, // Admin
    'model_name' => ['User', 'Article', 'Order', 'Group'], // Modelli disponibili
    'can_read' => true,
    'can_create' => true,
    'can_update' => true,
    'can_delete' => true,
]);

// Permessi ristretti per ruoli critici
ModelPermission::create([
    'backpack_role_id' => 1, // Admin
    'model_name' => ['BackpackRole', 'Role', 'ModelPermission'],
    'can_read' => true,
    'can_create' => false,  // ❌ Non può creare
    'can_update' => false,  // ❌ Non può modificare
    'can_delete' => false,  // ❌ Non può eliminare
]);
```

### Matrice Permessi Default

| Ruolo | Modelli Gestiti | Create | Read | Update | Delete |
|-------|-----------------|--------|------|--------|--------|
| **Developer** | TUTTI | ✅ | ✅ | ✅ | ✅ |
| **Admin** | Maggior parte | ✅ | ✅ | ✅ | ✅ |
| **Admin** | Ruoli critici | ❌ | ✅ | ❌ | ❌ |
| **Author** | Contenuti | ✅ | ✅ | ✅ | ✅ |
| **Author** | Altri modelli | ❌ | ✅ | ❌ | ❌ |
| **Guest** | Tutti | ❌ | ✅ | ❌ | ❌ |

## 🚀 Modelli Disponibili

### Gestione Contenuti
- `Article`: Articoli
- `Institutional`: Pagine istituzionali
- `Media`: File multimediali
- `Attachment`: Allegati
- `Page`: Pagine statiche

### Gestione Utenti
- `User`: Utenti del sistema
- `Group`: Gruppi utenti
- `Invite`: Inviti
- `Notification`: Notifiche

### E-commerce
- `Order`: Ordini
- `Ecommerce`: Configurazioni e-commerce
- `Category`: Categorie prodotti

### Sistema
- `BackpackRole`: Ruoli backend (CRITICO)
- `Role`: Ruoli frontend (CRITICO)
- `ModelPermission`: Permessi modelli (CRITICO)
- `Contact`: Contatti
- `Metadata`: Metadati SEO

## 🔒 Controlli di Sicurezza

### 1. **Autenticazione Backpack**
```php
// Middleware automatico su tutte le routes admin
Route::middleware(['backpack.auth'])->group(function () {
    // Routes protette
});
```

### 2. **Controlli Automatici CRUD**
```php
// Nel controller, setupPermissionChecks() applica automaticamente:
$this->crud->denyAccess(['list', 'create', 'update', 'delete']);

// Poi abilita solo le operazioni permesse
if ($this->userCan($modelName, 'read')) {
    $this->crud->allowAccess(['list', 'show']);
}
```

### 3. **Menu Dinamico**
```php
// In menu_items.blade.php
@if (ChecksBackpackPermissions::userCanAccessMenu('User'))
    <x-backpack::menu-item title="Users" :link="backpack_url('user')" />
@endif
```

## 🛠️ Debugging e Troubleshooting

### Verificare Permessi Utente Backend

```php
// In tinker
$user = User::find(1);
echo "Backpack Role ID: " . $user->backpack_role_id;

// Verificare permessi diretti
use Illuminate\Support\Facades\Gate;
$canRead = Gate::forUser($user)->allows('backpack-access-model', ['User', 'read']);
echo $canRead ? 'YES' : 'NO';
```

### Common Issues

1. **Menu Items Non Visibili**
   - Verificare `userCanAccessMenu()` in menu_items.blade.php
   - Controllare che il ruolo abbia permessi di lettura

2. **Accesso Negato a CRUD**
   - Verificare `setupPermissionChecks()` nel controller
   - Controllare permessi in `model_permissions`

3. **Utente Non Può Accedere**
   - Verificare `backpack_role_id` nell'utente
   - Controllare che il ruolo esista

## 🔄 Aggiungere Nuovi Ruoli

### 1. Creare il Ruolo
```php
BackpackRole::create([
    'name' => 'Editor',
    'description' => 'Content Editor',
]);
```

### 2. Configurare Permessi
```php
ModelPermission::create([
    'backpack_role_id' => $editorRole->id,
    'model_name' => ['Article', 'Media', 'Category'],
    'can_read' => true,
    'can_create' => true,
    'can_update' => true,
    'can_delete' => false, // Gli editor non possono eliminare
]);
```

### 3. Assegnare agli Utenti
```php
$user = User::find(1);
$user->backpack_role_id = $editorRole->id;
$user->save();
```

## 📝 Best Practices

1. **Principio del Minimo Privilegio**: Dai solo i permessi necessari
2. **Ruoli Specifici**: Crea ruoli per scopi specifici invece di uno generico
3. **Testa Sempre**: Verifica i permessi dopo ogni modifica
4. **Documenta**: Mantieni documentazione dei ruoli e permessi
5. **Backup**: Fai backup prima di modificare permessi critici

## ⚠️ Modelli Critici

### Attenzione Speciale
- `BackpackRole`: Gestione ruoli backend
- `Role`: Gestione ruoli frontend  
- `ModelPermission`: Gestione permessi
- `User`: Gestione utenti (può essere critico)

### Sicurezza
- Solo **Developer** dovrebbe avere accesso completo ai modelli critici
- **Admin** dovrebbe avere accesso limitato (solo lettura)
- Altri ruoli NON dovrebbero accedere ai modelli critici

## 🎉 Vantaggi del Sistema

- ✅ **Granularità**: Controllo preciso su ogni operazione
- ✅ **Flessibilità**: Facile aggiunta nuovi ruoli
- ✅ **Sicurezza**: Protezione modelli critici
- ✅ **Automazione**: Controlli automatici nei controller
- ✅ **UI Dinamica**: Menu che si adatta ai permessi
- ✅ **Audit Trail**: Possibilità di tracciare chi fa cosa

---

**Sistema enterprise-grade per gestione sicura! 🔐** 