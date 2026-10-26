<?php
/**
 * com_partnerdesk - shared field definitions
 *
 * Single source of truth for the brand submission structure.
 * Mirrors the FEC Brand Listing Sheet (11 sections).
 * Used by: site form, admin detail view, fact-sheet draft export.
 *
 * @copyright FreeExtraChips.com
 */

defined('_JEXEC') or die;

class PartnerdeskHelperFields
{
	/**
	 * Full sheet structure. Field types: text, textarea, select, email, url.
	 *
	 * @return array
	 */
	public static function getSections()
	{
		return array(

			array(
				'title'  => '1. Identity',
				'fields' => array(
					array('key' => 'brand_name',   'label' => 'Brand name',   'type' => 'text', 'required' => true,  'hint' => 'Example Casino'),
					array('key' => 'casino_url',   'label' => 'Casino URL',   'type' => 'url',  'required' => true,  'hint' => 'https://examplecasino.com/'),
					array('key' => 'established',  'label' => 'Established (launch date, YYYY-MM-DD)', 'type' => 'text', 'required' => false, 'hint' => '2024-05-23 - if only the year is certain: 2024 (month pending)'),
					array('key' => 'product_scope', 'label' => 'Product scope', 'type' => 'select', 'required' => false, 'options' => array('casino only', 'casino + sportsbook')),
				),
			),

			array(
				'title'  => '2. Corporate &amp; Licensing',
				'fields' => array(
					array('key' => 'owner_entity',      'label' => 'Owner / operating company (legal entity)', 'type' => 'text', 'required' => false, 'hint' => 'Example Gaming N.V.'),
					array('key' => 'company_reg',       'label' => 'Company registration no. &amp; jurisdiction', 'type' => 'text', 'required' => false, 'hint' => '163922 - Curacao'),
					array('key' => 'footer_entity',     'label' => 'Entity shown in the site footer', 'type' => 'text', 'required' => false, 'hint' => 'Must match T&amp;C - flag if it does not'),
					array('key' => 'licence_authority', 'label' => 'Licence authority', 'type' => 'text', 'required' => false, 'hint' => 'Curacao Gaming Authority (CGA)'),
					array('key' => 'licence_number',    'label' => 'Licence number', 'type' => 'text', 'required' => false, 'hint' => 'OGL/2024/0000/0000'),
					array('key' => 'licence_status_url', 'label' => 'Licence status &amp; public verification URL', 'type' => 'text', 'required' => false, 'hint' => 'Active - https://cert.cga.cw/...'),
					array('key' => 'sister_brands',     'label' => 'Sister brands / network', 'type' => 'textarea', 'required' => false, 'hint' => 'SisterBrand One, SisterBrand Two'),
					array('key' => 'platform_provider', 'label' => 'Platform / white-label provider (if any)', 'type' => 'text', 'required' => false, 'hint' => 'e.g. SoftSwiss, BetB2B, Delasport'),
				),
			),

			array(
				'title'  => '3. Markets &amp; Restrictions',
				'fields' => array(
					array('key' => 'restricted_countries',     'label' => 'Restricted countries (full T&amp;C list, ISO-2)', 'type' => 'textarea', 'required' => true, 'hint' => 'US, GB, NL, FR, ES, TR'),
					array('key' => 'subnational_restrictions', 'label' => 'Sub-national restrictions (if any)', 'type' => 'text', 'required' => false, 'hint' => 'e.g. Ontario (CA), specific US states'),
					array('key' => 'website_languages',        'label' => 'Website languages', 'type' => 'text', 'required' => false, 'hint' => 'English, German, French'),
					array('key' => 'geo_notes',                'label' => 'Geo notes / contradictions', 'type' => 'textarea', 'required' => false, 'hint' => 'e.g. TR restricted in T&amp;C but TRY offered at cashier'),
				),
			),

			array(
				'title'  => '4. Currencies',
				'fields' => array(
					array('key' => 'fiat_currencies',   'label' => 'Fiat currencies',   'type' => 'text', 'required' => false, 'hint' => 'EUR, USD, CAD, AUD, NOK, NZD'),
					array('key' => 'crypto_currencies', 'label' => 'Cryptocurrencies',  'type' => 'text', 'required' => false, 'hint' => 'BTC, ETH, LTC, USDT, DOGE - write none if unsupported'),
				),
			),

			array(
				'title'  => '5. Games &amp; Software',
				'fields' => array(
					array('key' => 'game_providers', 'label' => 'Game providers (full list)', 'type' => 'textarea', 'required' => false, 'hint' => 'Pragmatic Play, SpinLogic Gaming, Betsoft, NetEnt'),
					array('key' => 'game_count',     'label' => 'Game count (approx.)', 'type' => 'text', 'required' => false, 'hint' => '6500'),
					array('key' => 'casino_type',    'label' => 'Casino type', 'type' => 'text', 'required' => false, 'hint' => 'instant play, mobile, live dealer, download'),
					array('key' => 'live_casino',    'label' => 'Live casino', 'type' => 'text', 'required' => false, 'hint' => 'yes / no - provider if yes'),
				),
			),

			array(
				'title'  => '6. Bonuses',
				'fields' => array(
					array('key' => 'welcome_public',    'label' => 'Welcome bonus - standard public package', 'type' => 'text', 'required' => false, 'hint' => '100% up to 500 EUR + 200 FS'),
					array('key' => 'welcome_affiliate', 'label' => 'Welcome bonus - affiliate exclusive (+ code)', 'type' => 'text', 'required' => false, 'hint' => '150% up to 750 EUR + 100 FS (code FEC150)'),
					array('key' => 'no_deposit',        'label' => 'No-deposit bonus (if any, + code)', 'type' => 'text', 'required' => false, 'hint' => '25 FS on signup (code FEC25)'),
					array('key' => 'wagering_req',      'label' => 'Wagering requirement (state base: B or D+B)', 'type' => 'text', 'required' => false, 'hint' => '40x (D+B)'),
					array('key' => 'max_bet',           'label' => 'Max bet during wagering', 'type' => 'text', 'required' => false, 'hint' => '5 EUR'),
					array('key' => 'max_cashout',       'label' => 'Max cashout on bonus winnings', 'type' => 'text', 'required' => false, 'hint' => '10x deposit - state the general-terms fallback cap if the promo omits one'),
					array('key' => 'freespins_details', 'label' => 'Free spins: game, value, winnings cap', 'type' => 'text', 'required' => false, 'hint' => 'Book of Example, 0.10 EUR/spin, cap 100 EUR'),
					array('key' => 'sticky',            'label' => 'Sticky / non-sticky', 'type' => 'select', 'required' => false, 'options' => array('non-sticky', 'sticky', 'mixed - see notes')),
					array('key' => 'recurring_promos',  'label' => 'Recurring promotions', 'type' => 'textarea', 'required' => false, 'hint' => 'weekly reload 50%, cashback 10%'),
					array('key' => 'vip_program',       'label' => 'VIP / loyalty program', 'type' => 'text', 'required' => false, 'hint' => 'yes - 5 levels, comp points'),
				),
			),

			array(
				'title'  => '7. Payments',
				'fields' => array(
					array('key' => 'deposit_methods',    'label' => 'Deposit methods', 'type' => 'textarea', 'required' => false, 'hint' => 'Visa, Mastercard, Skrill, Neteller, crypto'),
					array('key' => 'withdrawal_methods', 'label' => 'Withdrawal methods', 'type' => 'textarea', 'required' => false, 'hint' => 'bank transfer, Skrill, Neteller, crypto'),
					array('key' => 'min_deposit',        'label' => 'Minimum deposit', 'type' => 'text', 'required' => false, 'hint' => '20 EUR'),
					array('key' => 'min_withdrawal',     'label' => 'Minimum withdrawal', 'type' => 'text', 'required' => false, 'hint' => '30 EUR'),
					array('key' => 'withdrawal_limits',  'label' => 'Withdrawal limits (daily / weekly / monthly)', 'type' => 'text', 'required' => false, 'hint' => '1,000 / 5,000 / 15,000 EUR'),
					array('key' => 'processing_time',    'label' => 'Withdrawal processing / pending time', 'type' => 'text', 'required' => false, 'hint' => '24-72h after approval'),
					array('key' => 'instalments',        'label' => 'Payout in instalments?', 'type' => 'text', 'required' => false, 'hint' => 'no - if yes, state the split policy explicitly'),
					array('key' => 'fees',               'label' => 'Fees on deposits or withdrawals', 'type' => 'text', 'required' => false, 'hint' => 'none / 2% on card withdrawals'),
					array('key' => 'kyc',                'label' => 'KYC / verification requirements', 'type' => 'textarea', 'required' => false, 'hint' => 'ID + proof of address before first withdrawal'),
				),
			),

			array(
				'title'  => '8. Customer Support',
				'fields' => array(
					array('key' => 'live_chat',         'label' => 'Live chat', 'type' => 'select', 'required' => true, 'options' => array('yes', 'no')),
					array('key' => 'chat_hours',        'label' => 'Live chat hours', 'type' => 'text', 'required' => false, 'hint' => '24/7 / business hours / other - state actual hours'),
					array('key' => 'support_languages', 'label' => 'Support languages', 'type' => 'text', 'required' => false, 'hint' => 'English, German'),
					array('key' => 'support_channels',  'label' => 'Support channels', 'type' => 'text', 'required' => false, 'hint' => 'live chat, email, phone'),
					array('key' => 'support_email',     'label' => 'Support email', 'type' => 'email', 'required' => false, 'hint' => 'support@examplecasino.com'),
					array('key' => 'support_phone',     'label' => 'Support phone', 'type' => 'text', 'required' => false, 'hint' => '+356 0000 0000 - write none if no phone line'),
				),
			),

			array(
				'title'  => '9. Responsible Gambling',
				'fields' => array(
					array('key' => 'rg_tools',       'label' => 'RG tools available', 'type' => 'text', 'required' => false, 'hint' => 'deposit limits, self-exclusion, cooling-off, reality check'),
					array('key' => 'min_age_rg_url', 'label' => 'Minimum age &amp; RG page URL', 'type' => 'text', 'required' => false, 'hint' => '18+ - https://examplecasino.com/responsible-gaming'),
				),
			),

			array(
				'title'  => '10. Affiliate Contact',
				'fields' => array(
					array('key' => 'aff_program_name', 'label' => 'Affiliate program name', 'type' => 'text', 'required' => true, 'hint' => 'Example Partners'),
					array('key' => 'aff_program_url',  'label' => 'Affiliate program URL', 'type' => 'url', 'required' => true, 'hint' => 'https://examplepartners.com/'),
					array('key' => 'am_name',          'label' => 'Affiliate manager - name', 'type' => 'text', 'required' => true, 'hint' => 'Jane Doe'),
					array('key' => 'am_email',         'label' => 'Affiliate manager - email', 'type' => 'email', 'required' => true, 'hint' => 'jane@examplepartners.com'),
					array('key' => 'am_messenger',     'label' => 'AM messenger (Skype / Telegram)', 'type' => 'text', 'required' => false, 'hint' => '@janedoe_partners'),
				),
			),

			array(
				'title'  => '11. Open Items',
				'fields' => array(
					array('key' => 'pending_items', 'label' => 'Pending / unverified items', 'type' => 'textarea', 'required' => false, 'hint' => 'List every field marked pending and why'),
					array('key' => 'notes',         'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'hint' => ''),
				),
			),

		);
	}

	/**
	 * Flat list of all fields.
	 *
	 * @return array
	 */
	public static function getAllFields()
	{
		$out = array();

		foreach (self::getSections() as $section)
		{
			foreach ($section['fields'] as $field)
			{
				$out[] = $field;
			}
		}

		return $out;
	}

	/**
	 * Human label of the status code.
	 *
	 * @param   integer  $status
	 * @return  string
	 */
	public static function statusLabel($status)
	{
		switch ((int) $status)
		{
			case 1:
				return 'Approved';
			case 2:
				return 'Rejected';
			default:
				return 'Pending';
		}
	}

	/**
	 * Build a fact-sheet DRAFT in Markdown from a submission row.
	 * Everything is flagged as single-source affiliate self-declaration:
	 * nothing here is publishable before first-party verification.
	 *
	 * @param   object  $item     Row from #__fec_brand_submissions
	 * @param   array   $payload  Decoded payload
	 * @return  string
	 */
	public static function buildFactsheetDraft($item, $payload)
	{
		$nl    = "\n";
		$brand = trim($item->brand_name) !== '' ? trim($item->brand_name) : 'Unknown brand';

		$get = function ($key) use ($payload) {
			return (isset($payload[$key]) && trim($payload[$key]) !== '') ? trim($payload[$key]) : 'pending';
		};

		$md  = '# ' . $brand . ' - Fact Sheet DRAFT (Partner Desk submission #' . (int) $item->id . ')' . $nl . $nl;
		$md .= '> SOURCE: affiliate manager self-declaration via FEC Partner Desk.' . $nl;
		$md .= '> CONFIDENCE: single_source (affiliate). Two-source rule NOT satisfied.' . $nl;
		$md .= '> Every value below requires first-party verification (live T&C fetch, licence register, cashier) before any publication or DB write.' . $nl . $nl;

		$md .= 'Brand: ' . $brand . $nl;
		$md .= 'Casino URL: ' . $get('casino_url') . $nl;
		$md .= 'Established: ' . $get('established') . ' (unverified - AM-declared)' . $nl;
		$md .= 'Operator cluster: pending - check CLUSTER_INDEX.md for ' . $get('owner_entity') . $nl;
		$md .= 'Submitted: ' . $item->created . ' UTC by ' . $get('am_name') . ' (' . $get('am_email') . ')' . $nl;
		$md .= 'Review status: ' . self::statusLabel($item->status) . $nl . $nl;

		foreach (self::getSections() as $section)
		{
			$md .= '## ' . html_entity_decode($section['title'], ENT_QUOTES, 'UTF-8') . $nl;

			foreach ($section['fields'] as $field)
			{
				$label = html_entity_decode($field['label'], ENT_QUOTES, 'UTF-8');
				$md .= '- ' . $label . ': ' . $get($field['key']) . $nl;
			}

			$md .= $nl;
		}

		$md .= '## DB Field Mapping (draft - verify before any SQL)' . $nl;
		$md .= '- jr_owner: ' . $get('owner_entity') . ' (pending first-party T&C verification)' . $nl;
		$md .= '- jr_network: ' . $get('aff_program_name') . ' (pending)' . $nl;
		$md .= '- jr_established: ' . $get('established') . ' (pending)' . $nl;
		$md .= '- jr_restrictedcountries: ' . $get('restricted_countries') . ' (IRON RULE: pending live T&C fetch + ISO-2 script with asserts)' . $nl;
		$md .= '- jr_livesupport: ' . ($get('live_chat') === 'yes' ? '*yes*' : ($get('live_chat') === 'no' ? '*no*' : 'pending')) . ' (pending verification)' . $nl;
		$md .= '- jr_phone: ' . $get('support_phone') . ' (pending)' . $nl;
		$md .= '- jr_email: ' . $get('support_email') . ' (pending)' . $nl . $nl;

		$md .= '## Open items declared by AM' . $nl;
		$md .= $get('pending_items') . $nl . $nl;

		$md .= '## Review meta' . $nl;
		$md .= '- Submission id: ' . (int) $item->id . $nl;
		$md .= '- IP: ' . $item->ip . $nl;
		$md .= '- User agent: ' . $item->user_agent . $nl;
		$md .= '- Internal notes: ' . (trim($item->admin_notes) !== '' ? $item->admin_notes : 'none') . $nl;

		return $md;
	}
}
