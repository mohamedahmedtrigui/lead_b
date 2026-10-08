<?php

namespace App\Domain\Scripts;

/**
 * Default wording of the qualification call script
 * ("Script d'appel – Qualification des demandes de transport", dialecte tunisien / français).
 *
 * Step keys, fields and option values are part of the code (they map to the
 * lead_qualifications columns). Everything a dispatcher reads (titles,
 * speech, questions, prompts, option labels, replies, tips) can be edited by
 * an admin and is stored in the `script_steps` table.
 *
 * - `responses`: what the dispatcher says when the client gives an answer
 *   (field => value => text), shown under the selected answer.
 * - Placeholders filled by the app: [Prénom] (dispatcher), [CLIENT],
 *   [DÉPART], [DESTINATION], [FRÉQUENCE], [HORAIRE], [NOMBRE],
 *   [TRAJET PARTAGÉ / INDIVIDUEL], [JOUR], [HEURE] (callback).
 */
class DefaultScript
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function steps(): array
    {
        return [
            [
                'key' => 'introduction',
                'title' => 'Introduction',
                'objective' => 'Qualifier la demande de transport du client et identifier la solution MiralDrive adaptée (≈ 5 min).',
                'script' => "Aslema, ena [Prénom] men MiralDrive, l'application de taxi. Habit naamel suivi 3la demande mta3 transport elli 3maltha récemment. Habina na3rfou akther 3la el besoin mte3ek w nchoufou chnowa el solution elli tnajem تناسبك.",
                'question' => '3andek 5 d9aye9 tawwa bech na7kiw 3liha ?',
                'prompts' => [],
                'options' => [
                    'call_availability' => self::opts(['YES' => 'Oui', 'CALLBACK' => 'Pas maintenant']),
                ],
                'responses' => [
                    'call_availability' => [
                        'YES' => 'Behy, merci barcha. Nbdéw.',
                        'CALLBACK' => 'Ma fama 7atta mochkel. Chneya lwa9t elli ynasebek bech n3awed n3ayetlek ?',
                    ],
                ],
                'tips' => 'Langue : dialecte tunisien / français. Si le client n’est pas disponible, notez le créneau qu’il propose.',
            ],
            [
                'key' => 'beneficiary',
                'title' => 'Pour qui est le transport',
                'objective' => 'Identifier qui utilisera le transport.',
                'script' => null,
                'question' => 'Bech nefhem akther el besoin mte3ek, el transport hedha bech يكون ليك enti, wala bech يكون l personne okhra ?',
                'prompts' => ['beneficiary_details' => 'Précisions (qui exactement, combien d’étudiants…)'],
                'options' => [
                    'beneficiary' => self::opts([
                        'SELF' => 'Pour lui-même',
                        'FAMILY' => 'Famille',
                        'EMPLOYEES' => 'Employés',
                        'STUDENTS' => 'Étudiants',
                        'COMPANY' => 'Entreprise',
                        'OTHER' => 'Autre personne',
                    ]),
                ],
                'responses' => [
                    'beneficiary' => [
                        'FAMILY' => "D'accord. Bech يكون l chkoun exactement ?",
                        'EMPLOYEES' => "D'accord, donc el besoin مرتبط b transport mta3 les employés.",
                        'STUDENTS' => "D'accord. 9addeh men étudiant تقريباً ?",
                    ],
                ],
                'tips' => null,
            ],
            [
                'key' => 'need',
                'title' => 'Type de déplacement',
                'objective' => 'Comprendre la nature du déplacement.',
                'script' => null,
                'question' => 'W chnowa naw3 el déplacement elli تستحق 3lih el transport ?',
                'prompts' => ['transport_need_details' => 'Précisions sur le besoin'],
                'options' => [
                    'transport_need' => self::opts([
                        'PERSONAL' => 'Personnel',
                        'FAMILY' => 'Familial',
                        'SHARED' => 'Partagé',
                        'EMPLOYEE' => 'Employés (B2B)',
                        'STUDENT' => 'Étudiants / scolaire',
                        'RECURRING' => 'Trajet régulier',
                        'OTHER' => 'Autre',
                    ]),
                ],
                'responses' => [],
                'tips' => 'Identifier s’il s’agit d’un besoin Personnel ou B2B.',
            ],
            [
                'key' => 'route',
                'title' => 'Trajet et fréquence',
                'objective' => 'Obtenir le trajet exact, les horaires et la fréquence.',
                'script' => 'Tawwa bech na3rfou el trajet exactement.',
                'question' => 'Men win généralement bech tebda el course ?',
                'prompts' => [
                    'destination' => 'W win bech تكون destination ?',
                    'trip_type' => 'El trajet bech يكون aller seulement wala aller-retour ?',
                    'departure_time' => 'Heure de départ',
                    'return_time' => 'W pour el retour, généralement fi ana wa9t ?',
                    'frequency' => 'Fréquence',
                    'days_of_week' => 'Jours concernés',
                    'trips_per_day' => 'Trajets par jour',
                    'trips_per_week' => 'Trajets par semaine',
                    'is_recurring' => 'Besoin récurrent',
                ],
                'options' => [
                    'trip_type' => self::opts(['ONE_WAY' => 'Aller seulement', 'ROUND_TRIP' => 'Aller-retour']),
                    'frequency' => self::opts([
                        'ONE_TIME' => 'Ponctuel (une seule fois)',
                        'SEVERAL_TIMES_PER_WEEK' => 'Plusieurs fois par semaine',
                        'DAILY' => 'Tous les jours',
                        'FIXED_DAYS' => 'Jours fixes',
                    ]),
                    'days_of_week' => self::opts([
                        'MON' => 'Lun', 'TUE' => 'Mar', 'WED' => 'Mer', 'THU' => 'Jeu',
                        'FRI' => 'Ven', 'SAT' => 'Sam', 'SUN' => 'Dim',
                    ]),
                ],
                'responses' => [],
                'tips' => 'Demander également la fréquence : combien de fois par jour, par semaine, ou les deux.',
            ],
            [
                'key' => 'passengers',
                'title' => 'Passagers',
                'objective' => 'Connaître le nombre de personnes à transporter.',
                'script' => null,
                'question' => 'W normalement, 9addeh men personne bech تستعمل el transport ?',
                'prompts' => [
                    'passengers_count' => '9addeh تقريباً ?',
                    'total_employees' => 'W 9addeh men employé تقريباً bech يكون معني بالنقل ?',
                    'estimated_passengers_per_trip' => 'W fi kol trajet, 9addeh men personne تقريباً bech تركب ?',
                ],
                'options' => [],
                'responses' => [],
                'tips' => 'Si plusieurs personnes : demander le nombre approximatif. Si entreprise : employés concernés et passagers par trajet.',
            ],
            [
                'key' => 'shared',
                'title' => 'Transport partagé',
                'objective' => 'Proposer le transport partagé (personne seule, hors B2B).',
                'script' => "Nheb zeda n9ollek 3la possibilité okhra elli tnajem تكون مناسبة lel besoin mte3ek. MiralDrive tnajem torganizi des trajets partagés ki tkoun les trajets w les horaires compatibles.\n\nYa3ni tnajem تركب m3a personne okhra elli 3andha تقريباً nafs direction, fi cadre organisé.",
                'question' => 'El formule hedhi tnajem تكون مناسبة ليك ?',
                'prompts' => ['shared_direction' => 'Sens du partage'],
                'options' => [
                    'shared_transport' => self::opts(['YES' => 'Oui', 'MAYBE' => 'Peut-être', 'NO' => 'Non']),
                    'shared_direction' => self::opts(['OUTBOUND' => 'Aller', 'RETURN' => 'Retour', 'BOTH' => 'Les deux']),
                ],
                'responses' => [
                    'shared_transport' => [
                        'YES' => "Behy. El partage ynajem يكون fi l'aller, fi retour, wala les deux ?",
                        'MAYBE' => "D'accord, n5alliwh كoption w nchoufou حسب el trajet.",
                        'NO' => "D'accord, fhemtik. تفضّل trajet individuel.",
                    ],
                ],
                'tips' => 'À proposer uniquement s’il s’agit d’une personne seule (hors B2B) : l’étape est masquée sinon.',
            ],
            [
                'key' => 'experience',
                'title' => 'Expérience avec MiralDrive ou d’autres services',
                'objective' => 'Savoir si le client connaît MiralDrive ou un autre service.',
                'script' => null,
                'question' => "Est-ce que sta3malt l'application mte3na MiralDrive, wala 3andek expérience m3a d'autres services de transport ?",
                'prompts' => [
                    'experience_rating' => 'Note de l’expérience MiralDrive',
                    'experience_feedback' => 'Détails de l’expérience',
                    'improvement_request' => 'Améliorations souhaitées',
                ],
                'options' => [
                    'used_miraldrive' => self::opts(['YES' => 'Oui, a déjà utilisé MiralDrive', 'NO' => 'Non', 'UNKNOWN' => 'Ne sait pas']),
                ],
                'responses' => [],
                'tips' => 'Si oui : noter les détails (et le contexte B2B le cas échéant). Les autres services utilisés se notent à l’étape suivante.',
            ],
            [
                'key' => 'current_solution',
                'title' => 'Solution actuelle',
                'objective' => 'Identifier la solution actuelle et les difficultés du client.',
                'script' => null,
                'question' => 'Est-ce que 9a3ed tal9a fi sou3ouba fel transport lyouma ?',
                'prompts' => [
                    'current_provider_details' => 'Précisions (service utilisé, coût…)',
                    'pain_point' => 'Difficultés rencontrées',
                    'customer_preference' => 'Thanna mr m3a MiralDrive lyoum…',
                ],
                'options' => [
                    'current_provider' => self::opts([
                        'NO' => 'Aucun service',
                        'TRADITIONAL_TAXI' => 'Taxi traditionnel',
                        'APPLICATION' => 'Application',
                        'PRIVATE_TRANSPORT' => 'Transport privé',
                        'TRANSPORT_COMPANY' => 'Société de transport',
                        'PRIVATE_DRIVER' => 'Chauffeur privé',
                        'OTHER' => 'Autre',
                    ]),
                ],
                'responses' => [],
                'tips' => 'Faites reformuler la difficulté principale : c’est la clé de la proposition.',
            ],
            [
                'key' => 'b2b',
                'title' => 'Qualification entreprise (B2B)',
                'objective' => 'Qualifier le besoin entreprise et l’interlocuteur.',
                'script' => 'Fhemt. Donc el besoin hedha مرتبط b entreprise. Bech نسألك quelques questions sghar باش نفهمو exactement el besoin.',
                'question' => 'W enti, est-ce que enti الشخص elli ya5ou el décision بالنسبة lel service hedha ?',
                'prompts' => [
                    'company_name' => 'Chnowa esm el société ?',
                    'company_size' => 'Taille de l’entreprise (salariés)',
                    'employees_concerned' => '9addeh men employé تقريباً يكون معني بالنقل ?',
                    'trips_per_day' => '9addeh men trajet تقريباً تستحقو fi nhar ?',
                    'b2b_same_schedule' => 'Les trajets يكونو généralement fi nafs el horaire ?',
                    'decision_maker_name' => 'Personne à contacter',
                ],
                'options' => [
                    'decision_role' => self::opts([
                        'DECISION_MAKER' => 'Oui, c’est le décideur',
                        'INFLUENCER' => 'Non, sera notre vis-à-vis',
                        'NEEDS_APPROVAL' => 'Non, autre personne à contacter',
                    ]),
                ],
                'responses' => [
                    'decision_role' => [
                        'INFLUENCER' => 'Est-ce que enti bech tkoun vis-à-vis m3ana, wala bech nkounou en contact m3a personne okhra ?',
                        'NEEDS_APPROVAL' => 'Est-ce que enti bech tkoun vis-à-vis m3ana, wala bech nkounou en contact m3a personne okhra ?',
                    ],
                ],
                'tips' => 'Section à utiliser uniquement si le besoin est B2B (affichée automatiquement).',
            ],
            [
                'key' => 'recap',
                'title' => 'Récapitulatif et validation',
                'objective' => 'Reformuler le besoin et le faire valider par le client.',
                'script' => 'Behy, n5alli nraja3 m3ak elli fhemtou bech نتأكد elli kol chay صحيح.',
                'question' => "Enti تستحق transport men [DÉPART] lel [DESTINATION], [FRÉQUENCE], généralement [HORAIRE], pour [NOMBRE] personne(s), w [TRAJET PARTAGÉ / INDIVIDUEL]. C'est bien ça ?",
                'prompts' => [],
                'options' => [
                    'recap_confirmed' => self::opts(['YES' => 'Oui, c’est bien ça', 'NO' => 'Non, à corriger']),
                ],
                'responses' => [
                    'recap_confirmed' => [
                        'YES' => 'Parfait.',
                        'NO' => 'Corrigez l’information concernée (cliquez sur l’étape dans la barre de progression), puis refaites valider.',
                    ],
                ],
                'tips' => 'Les éléments entre crochets sont remplis automatiquement avec les réponses du client.',
            ],
            [
                'key' => 'qualification',
                'title' => 'Évaluation (agent)',
                'objective' => 'Évaluer l’opportunité — étape interne, rien à dire au client.',
                'script' => null,
                'question' => null,
                'prompts' => [
                    'wants_quotation' => 'Le client demande une proposition / un devis',
                    'wants_callback' => 'Le client souhaite être rappelé',
                    'priority_stars' => 'Votre évaluation de la priorité commerciale',
                    'main_priority' => 'Priorité exprimée par le client (si mentionnée)',
                ],
                'options' => [
                    'main_priority' => self::opts([
                        'PRICE' => 'Le prix',
                        'PUNCTUALITY' => 'La ponctualité',
                        'COMFORT' => 'Le confort',
                        'RELIABILITY' => 'La fiabilité',
                        'ORGANIZATION' => 'L’organisation',
                    ]),
                ],
                'responses' => [],
                'tips' => 'Le score d’intérêt est calculé automatiquement. Les étoiles reflètent votre ressenti.',
            ],
            [
                'key' => 'closing',
                'title' => 'Clôture de l’appel',
                'objective' => 'Conclure selon la situation du client et définir la prochaine action.',
                'script' => null,
                'question' => null,
                'prompts' => [
                    'summary_note' => 'Résumé interne de l’appel (trajet, horaires, fréquence, passagers, partage, solution actuelle, motivation)',
                    'next_action' => 'Situation du client / prochaine action',
                    'callback_at' => 'Date et heure du rappel',
                ],
                'options' => [
                    'next_action' => self::opts([
                        'QUALIFIED' => 'Client intéressé',
                        'SEND_QUOTATION' => 'Devis',
                        'CALLBACK' => 'Rappel (callback)',
                        'TRANSFER_TO_SALES' => 'Transférer au commercial',
                        'FOLLOW_UP' => 'Relance / suivi',
                        'NOT_INTERESTED' => 'Pas intéressé',
                        'NRP' => 'NRP (communication coupée)',
                    ]),
                ],
                'responses' => [
                    'next_action' => [
                        'QUALIFIED' => "Behy, merci barcha 3la wa9tek. Tawwa 3andna les informations nécessaires bech نفهمو exactement el besoin mte3ek. L'équipe MiralDrive تنجم تكمل معاك حسب el solution elli تناسبك.",
                        'TRANSFER_TO_SALES' => "Behy, merci barcha 3la wa9tek. Tawwa 3andna les informations nécessaires bech نفهمو exactement el besoin mte3ek. L'équipe MiralDrive تنجم تكمل معاك حسب el solution elli تناسبك.",
                        'FOLLOW_UP' => "Behy, merci barcha 3la wa9tek. Tawwa 3andna les informations nécessaires bech نفهمو exactement el besoin mte3ek. L'équipe MiralDrive تنجم تكمل معاك حسب el solution elli تناسبك.",
                        'SEND_QUOTATION' => 'Behy, bech نخليو الطلب متاعك يتعالج w يرجعولك بالنسبة lel détails mta3 el proposition.',
                        'CALLBACK' => "D'accord, n3awed n3ayetlek [JOUR] fi [HEURE]. Merci barcha 3la wa9tek.",
                        'NOT_INTERESTED' => 'Ma fama 7atta mochkel. Merci 3la wa9tek w 3la توضيحك. Nchallah nharek zin.',
                    ],
                ],
                'tips' => 'Exemple de résumé : « Client recherche transport quotidien Sfax → centre-ville, départ 07h30, retour 17h30, 5 jours/semaine. 2 personnes. »',
            ],
            [
                'key' => 'summary',
                'title' => 'Récapitulatif interne',
                'objective' => 'Vérifier les informations avant de clôturer le lead.',
                'script' => null,
                'question' => null,
                'prompts' => [],
                'options' => [],
                'responses' => [],
                'tips' => 'Relisez le récapitulatif. Cliquez sur « Modifier » pour corriger une section avant de valider.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return collect(self::steps())->firstWhere('key', $key);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::steps(), 'key');
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, array{value: string, label: string}>
     */
    private static function opts(array $labels): array
    {
        return collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
