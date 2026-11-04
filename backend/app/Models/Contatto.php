<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Contatto extends Model
{
	use CrudTrait, HasDisplayAttributes;

<<<<<<< HEAD
	protected $fillable = ["email", "oggetto", "messaggio"];
=======
    /**
     * Subjects a contact request can have: value => label stored in "oggetto".
     * Shared by ContattoRequest (validation) and ContattoController.
     */
    public const OGGETTI = [
        'informazioni' => 'Richiesta informazioni',
        'assistenza' => 'Assistenza',
        'altro' => 'Altro',
    ];

    protected $fillable = ['email', 'oggetto', 'messaggio'];
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	protected $table = "contatti";

<<<<<<< HEAD
	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->email;
	}
=======
    // see HasDisplayAttributes for full doc
    public function getDisplayAttribute(): string
    {
        // contatti.email is nullable: fall back to the record id.
        return $this->email ?: '#'.$this->getKey();
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
