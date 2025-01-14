<?php

namespace Database\Seeders;

use App\Models\SeoMetaInformation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeoMetaInformationSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		SeoMetaInformation::create([
			"it" => "All Together Pay: l'app che mancava!",
			"en" => "All Together Pay: the app that was missing!",
			"code" => "title",
		]);
		SeoMetaInformation::create([
			"it" =>
				"All Together Pay è il servizio che ti permette di suddividere i pagamenti tra gruppi, privati e pubblici, in maniera semplice e senza problemi!",
			"en" =>
				"All Together Pay is the service that allows you to split payments among groups, private and public, easily and hassle-free!",
			"code" => "description",
		]);
	}
}
