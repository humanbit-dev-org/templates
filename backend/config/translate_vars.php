<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Models used to auto-generate placeholder registry from $fillable
    |--------------------------------------------------------------------------
    */
	"models" => [
		"user" => \App\Models\User::class,
	],

	/*
    |--------------------------------------------------------------------------
    | Global blacklist (fields excluded from placeholders)
    |--------------------------------------------------------------------------
    */
	"blacklist" => [
		"id",
		"created_at",
		"updated_at",
		"deleted_at",
		"password",
		"remember_token",
		"token",
		"token_expire",
		"token_verified",
		"email_verified_at",
	],

	/*
    |--------------------------------------------------------------------------
    | Optional per-field overrides
    |--------------------------------------------------------------------------
    | You can redefine labels/description/model for generated keys.
    | Example:
    | 'overrides' => [
    |   'surname' => ['label_it' => 'Cognome', 'label_en' => 'Surname'],
    | ],
    */
	"overrides" => [],

	/*
    |--------------------------------------------------------------------------
    | Frontend-only variables (calculated in Next.js, not from DB models)
    |--------------------------------------------------------------------------
    | These are shown as a separate "Frontend" group in the variable picker.
    | On the frontend, pass a matching key in the vars object passed to
    | interpolateHtml() / interpolateNodes().
    |
    | Example:
    |   'giorni_rimanenti' => [
    |       'label_it'    => 'Giorni rimanenti',
    |       'label_en'    => 'Days left',
    |       'description' => 'Giorni alla scadenza, calcolati lato frontend',
    |   ],
    */
	"frontend_vars" => [],
];
