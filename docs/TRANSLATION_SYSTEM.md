# Sistema di Traduzione Automatico - Versione Migliorata 🚀

## 🎯 Panoramica

Il sistema di traduzione è completamente automatizzato e sincronizza le traduzioni tra i file JSON nel frontend e il database Laravel, con **backup automatico**, **gestione traduzioni globali** e **controlli di sicurezza**.

## 🔄 Come Funziona

### Esportazione (Database → JSON)
- **Trigger**: Quando si salvano/cancellano traduzioni nel backend Backpack
- **Servizio**: `TranslateExportService::exportTranslations()`
- **Output**: File `frontend/lang/it.json` e `frontend/lang/en.json`
- **Traduzioni globali**: `page_id = null` → esportate come `"all"`

### Importazione (JSON → Database)
- **Trigger**: Automatico quando si accede al backend (se necessario)
- **Servizio**: `TranslateImportService::importTranslations()`
- **Input**: File `frontend/lang/it.json` e `frontend/lang/en.json`
- **Backup**: Automatico prima di ogni importazione
- **Traduzioni globali**: `"all"` → importate con `page_id = null`

## 📁 Struttura JSON

```json
{
  "home": {
    "hero_title": {
      "it": "Acquistare insieme senza pensieri",
      "text_it": "Descrizione dettagliata",
      "en": "Buy together without worries",
      "text_en": "Detailed description"
    }
  },
  "all": {
    "send": {
      "it": "Invia",
      "text_it": "",
      "en": "Send", 
      "text_en": ""
    }
  }
}
```

## 🛠️ Comandi Disponibili

### Importazione Manuale
```bash
# Importa solo se i JSON sono più recenti del DB
php artisan translations:import

# Forza l'importazione anche se non necessaria
php artisan translations:import --force

# Con output dettagliato
php artisan translations:import --force -v
```

### Seeding
```bash
# Popola il database con le traduzioni dai JSON
php artisan db:seed --class=TranslateSeeder --force
```

## 🔒 Controlli di Sicurezza

### 1. **Backup Automatico**
- **Posizione**: `storage/backups/translations/`
- **Formato**: `it_YYYY-MM-DD_HH-mm-ss.json`
- **Retention**: Mantiene ultimi 5 backup
- **Ripristino**: Automatico in caso di errore

### 2. **Controlli di Timing**
- ❌ **NON esegue** durante migrations
- ❌ **NON esegue** durante seeder 
- ❌ **NON esegue** se file JSON mancanti
- ✅ **Esegue** solo all'accesso web normale

### 3. **Gestione Traduzioni Globali**
- `page_id = null` in DB ↔ `"all"` in JSON
- Traduzioni condivise tra tutte le pagine
- Esempi: pulsanti, messaggi comuni

## 📊 Quando Avviene l'Importazione

1. **Accesso al backend**: Solo se file JSON più recenti del DB
2. **Comando manuale**: `translations:import` o `--force`
3. **Seeding**: Durante `TranslateSeeder` (forza import)
4. **Mai durante**: Migration, seeder di altri dati

## 🚨 Risoluzione Problemi

### Le traduzioni non si aggiornano
```bash
# Verifica stato
php artisan translations:import -v

# Forza aggiornamento
php artisan translations:import --force -v

# Verifica traduzioni globali
php artisan tinker --execute="
echo 'Globali: ' . App\Models\Translate::whereNull('page_id')->count();
echo '\nCon pagina: ' . App\Models\Translate::whereNotNull('page_id')->count();
"
```

### Errori durante importazione
```bash
# Verifica backup
ls -la storage/backups/translations/

# Controlla log
tail -f storage/logs/laravel.log

# Ripristino manuale (se necessario)
cp storage/backups/translations/it_LATEST.json frontend/lang/it.json
cp storage/backups/translations/en_LATEST.json frontend/lang/en.json
```

### File JSON corrotti
- Il sistema ripristina automaticamente dal backup
- Backup creato prima di ogni import
- Log dettagliati in `storage/logs/laravel.log`

## ✨ Caratteristiche Avanzate

### 🔄 **Sincronizzazione Bidirezionale**
- Backend → JSON (export automatico)
- JSON → Backend (import intelligente)
- Single source of truth: sempre i JSON

### 🛡️ **Prevenzione Errori**
- Eventi disabilitati durante import (no loop)
- Backup/restore automatico
- Controlli integrità JSON
- Gestione errori graceful

### 📈 **Performance**
- Import solo se necessario (timestamp check)
- Upsert intelligente (no duplicati)
- Pulizia backup automatica
- Log ottimizzati

## 💡 Note per Sviluppatori

### Traduzioni Globali
```php
// Nel database
Translate::whereNull('page_id')->get(); // Globali

// Nel JSON
"all": {
  "button_save": {"it": "Salva", "en": "Save"}
}
```

### Timing Integration
Il sistema **NON** interferisce con:
- Migrations (`php artisan migrate`)
- Seeder di altri dati
- Comandi CLI personalizzati

### Backup Management
```php
// Manuale
TranslateImportService::backupJsonFiles();

// Verifica
TranslateImportService::shouldImport(); // false durante migration
```

---

## 🎉 Vantaggi della Versione Migliorata

1. ✅ **Gestione "all"**: Traduzioni globali funzionano perfettamente
2. ✅ **Backup sicuro**: Nessuna perdita di dati possibile
3. ✅ **Timing intelligente**: Zero conflitti con migration/seeder
4. ✅ **Error recovery**: Ripristino automatico da backup
5. ✅ **Performance**: Import solo quando necessario
6. ✅ **Monitoring**: Log dettagliati per debug

**Sistema robusto e production-ready! 🚀** 