<?php

namespace Database\Seeders;

use App\Models\Translate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TranslateSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		Translate::create([
			"it" => "Accedi",
			"en" => "Sign in",
			"code" => "sign_in",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Registrati",
			"en" => "Sign up",
			"code" => "sign_up",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Email verificata con successo!",
			"en" => "Email successfully verified!",
			"code" => "email_verified",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Chi siamo",
			"en" => "About us",
			"code" => "about_us",
			"page" => "all",
		]);
		Translate::create([
			"text_it" =>
				"Siamo una banda di scalmanati e combinaguai, ma oggi ti risolviamo lo sbatti della vita: che sia saldare un debito con il tuo gruppo di amici, dividere le spese del viaggio a Manila con la tua dolce metà, o definire la lista dei regali di nozze di tuo zio Carmelo...",
			"text_en" =>
				"We’re a bunch of troublemakers, but today we’re here to solve life’s biggest headaches: whether it’s settling a debt with your group of friends, splitting the costs of a trip to Manila with your better half, or organizing Uncle Carmelo’s wedding gift list...",
			"code" => "about_us_text",
			"page" => "all",
		]);
		Translate::create([
			"it" => "con All Together Pay i conti tornano sempre, e le relazioni restano intatte.",
			"en" => "with All Together Pay, the numbers always add up, and relationships stay strong.",
			"code" => "about_us_text_2",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Che cos'è All Together Pay?",
			"en" => "What is All Together Pay?",
			"code" => "faq_1_q",
			"page" => "all",
		]);
		Translate::create([
			"text_it" =>
				"All Together Pay è il servizio che ti permette di suddividere i pagamenti tra gruppi, privati e pubblici, in maniera semplice e senza problemi!",
			"text_en" =>
				"All Together Pay is the service that allows you to split payments among groups, private and public, easily and hassle-free!",
			"code" => "faq_1_a",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Come funziona?",
			"en" => "How does it work?",
			"code" => "faq_2_q",
			"page" => "all",
		]);
		Translate::create([
			"text_it" =>
				"Utilizzando il metodo di pagamento che preferisci, chiediamo una pre-autorizzazione al tuo conto corrente, e solo se l'ordine viene confermato, la somma di denaro ti viene prelevata.",
			"text_en" =>
				"By using the payment method you prefer, we ask for a pre-authorization to your account, and only if the order is confirmed, the amount of money is taken from you.",
			"code" => "faq_2_a",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Funziona su tutti gli ecommerce?",
			"en" => "Does it work on all e-commerce?",
			"code" => "faq_3_q",
			"page" => "all",
		]);
		Translate::create([
			"text_it" =>
				"Il servizio è al momento presente su una lista di ecommerce partner, che puoi trovare nell'apposita pagina.",
			"text_en" =>
				"The service is currently available on a list of ecommerce partners, which you can find on the respective page.",
			"code" => "faq_3_a",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Che cosa sono i gruppi pubblici?",
			"en" => "What are the public groups?",
			"code" => "faq_4_q",
			"page" => "all",
		]);
		Translate::create([
			"text_it" =>
				"Oltre ai normali gruppi privati sono presenti dei gruppi di acquisto aperti, che fanno si appoggiano a dei magazzini locali.",
			"text_en" =>
				"In addition to the normal private groups, there are open purchase groups that are based on local stores.",
			"code" => "faq_4_a",
			"page" => "all",
		]);
		Translate::create([
			"it" => "Per quanto tempo viene bloccata la merce che sto acquistando insieme al mio gruppo?",
			"en" => "How long does it take the goods I'm buying with my group to be blocked?",
			"code" => "faq_5_q",
			"page" => "all",
		]);
		Translate::create([
			"text_it" =>
				"I tempi di blocco delle merce sono variabili e dipendono dal negozio e dal tipo di bene che si sta acquistando. Maggiori informazioni sono presenti sulle note dell'ecommerce selezionato.",
			"text_en" =>
				"The blocking times of the goods are variable and depend on the store and the type of goods you are buying. More information is available on the notes of the selected ecommerce.",
			"code" => "faq_5_a",
			"page" => "all",
		]);
	}
}
