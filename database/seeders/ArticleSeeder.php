<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		// Article::factory()->count(5)->create();
		Article::create([
			"title_it" => "Gabriel Silveri",
			"title_en" => "Gabriel Silveri",
			"subtitle_it" => "Sviluppatore Frontend",
			"subtitle_en" => "Frontend Developer",
			"body_it" =>
				'Gabriel è un ninja meticoloso, ogni suo lavoro è curato nei minimi dettagli. Per lui, il codice deve essere impeccabile: mai e poi mai "alla brutta di Dio".',
			"body_en" =>
				'Gabriel is a thorough ninja, with every task completed with the utmost precision. For him, the code must be flawless: never, ever "half-hearted".',
			"published" => true,
			"strillo" => true,
		]);
		Article::create([
			"title_it" => "Andrea Ponzano",
			"title_en" => "Andrea Ponzano",
			"subtitle_it" => "Project Manager",
			"subtitle_en" => "Project Manager",
			"body_it" =>
				"Techno in lontananza? Probabilemente si tratta di Andrea. Tra un rave e l'altro però fa anche il project manager. E fa musica, musica che spacca. Il suo sogno proibito? Trello.",
			"body_en" =>
				"Techno in the distance? It's probably Andrea. Between raves, he also works as a project manager. And he makes music, music that slaps. His forbidden dream? Trello.",
			"published" => true,
			"strillo" => true,
		]);
		Article::create([
			"title_it" => "Christian Solia",
			"title_en" => "Christian Solia",
			"subtitle_it" => "Fondatore e CTO",
			"subtitle_en" => "Founder and CTO",
			"body_it" =>
				"Ecco Christian, il fondatore e il CTO, che porta avanti la baracca con il suo spirito pratico e il suo carisma. Non fare domande e passagli un Barbera o dello zenzero candito.",
			"body_en" =>
				"Here’s Christian, the founder and CTO, steering the ship with his practical mindset and charisma. Don’t ask questions, just pass him a Barbera or some candied ginger.",
			"published" => true,
			"strillo" => true,
		]);
		Article::create([
			"title_it" => "Luca Giarrusso",
			"title_en" => "Luca Giarrusso",
			"subtitle_it" => "Sviluppatore Frontend",
			"subtitle_en" => "Frontend Developer",
			"body_it" =>
				"Luca tra qualche mese vincerà un paio di milioni di euro. Come? Un mago non rivela i suoi segreti. Nel frattempo supporta Gabriel con grande passione, senso stilistico e volgarità.",
			"body_en" =>
				"In a few months, Luca will win a couple of million euros. How? A magician never reveals his secrets. In the meantime, he supports Gabriel with great passion, style, and a touch of vulgarity.",
			"published" => true,
			"strillo" => true,
		]);
		Article::create([
			"title_it" => "Andrea Borreca",
			"title_en" => "Andrea Borreca",
			"subtitle_it" => "Sviluppatore Backend",
			"subtitle_en" => "Backend Developer",
			"body_it" =>
				'Andrea è lo sponsor non ufficiale dei Pocket Coffee. Il suo motto? "Nel tempo libero piango": un grande portatore di brio, esatto. Gli piace sbattere la testa sulle cose... metaforicamente.',
			"body_en" =>
				'Andrea is the unofficial sponsor of Pocket Coffee. His motto? "In my free time, I cry": a true bringer of joy, exactly. He likes banging his head against things... metaphorically.',
			"published" => true,
			"strillo" => true,
		]);
	}
}
