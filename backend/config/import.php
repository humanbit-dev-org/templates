<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ABI prefix for NDG lookups
    |--------------------------------------------------------------------------
    |
    | The automatic CSV imports (TransatoWLDaCC, RapportiFidiEstinti_E_ATPAperti)
    | carry a bare NDG, while users are stored as "{abi}-{ndg}". Today only Banco
    | di Desio feeds these files, so the prefix is its ABI — 03440.
    |
    | Set IMPORT_ABI_PREFIX per instance rather than editing this: the value is
    | deployment data, not behaviour, and each tenant is already its own
    | deployment. The lookup falls back to the bare NDG when the prefixed id
    | does not exist, so a wrong value degrades instead of breaking.
    |
    */

    'abi_prefix' => env('IMPORT_ABI_PREFIX', '03440'),

];
