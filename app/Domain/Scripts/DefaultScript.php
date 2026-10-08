<?php

namespace App\Domain\Scripts;

/**
 * Default wording of the qualification call script.
 *
 * Step keys, fields and option values are part of the code (they map to the
 * lead_qualifications columns). Everything a dispatcher reads (titles,
 * script, questions, prompts, option labels, tips) can be edited by an admin
 * and is stored in the `script_steps` table.
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
                'objective' => 'Se présenter et obtenir l\'accord du client pour 5 minutes d\'échange.',
                'script' => 'Bonjour, je vous appelle de la part de MiralDrive concernant votre demande de transport. Vous avez récemment manifesté un intérêt pour nos solutions de transport programmé. Je voulais prendre quelques minutes avec vous pour mieux comprendre votre besoin et voir quelle solution pourrait vous convenir.',
                'question' => 'Vous avez environ 5 minutes pour qu\'on regarde ça ensemble ?',
                'prompts' => [],
                'options' => [
                    'call_availability' => self::opts(['YES' => 'Oui, on peut en parler', 'CALLBACK' => 'Pas maintenant, rappeler plus tard']),
                ],
                'tips' => 'Souriez, parlez calmement. Si le client n\'est pas disponible, proposez un créneau de rappel précis.',
            ],
            [
                'key' => 'beneficiary',
                'title' => 'Pour qui est le transport',
                'objective' => 'Identifier la personne (ou le groupe) qui a besoin du transport.',
                'script' => 'Merci beaucoup ! Pour commencer, j\'aimerais bien comprendre votre besoin.',
                'question' => 'Vous recherchez ce transport pour vous-même ou pour quelqu\'un d\'autre ?',
                'prompts' => ['beneficiary_details' => 'Précisions (ex. : 2 enfants scolarisés, équipe de nuit...)'],
                'options' => [
                    'beneficiary' => self::opts([
                        'SELF' => 'Pour moi-même',
                        'FAMILY' => 'Pour ma famille / mes enfants',
                        'EMPLOYEES' => 'Pour mes employés',
                        'STUDENTS' => 'Pour des étudiants / élèves',
                        'COMPANY' => 'Pour mon entreprise',
                        'OTHER' => 'Autre',
                    ]),
                ],
                'tips' => 'Écoutez sans interrompre. Si le client parle d\'employés ou d\'entreprise, le module B2B s\'activera automatiquement.',
            ],
            [
                'key' => 'need',
                'title' => 'Type de déplacement',
                'objective' => 'Comprendre la nature du déplacement.',
                'script' => 'D\'accord, je vois.',
                'question' => 'Quel type de déplacement recherchez-vous principalement ?',
                'prompts' => ['transport_need_details' => 'Précisions sur le besoin'],
                'options' => [
                    'transport_need' => self::opts([
                        'PERSONAL' => 'Déplacement personnel',
                        'FAMILY' => 'Transport familial',
                        'SHARED' => 'Transport partagé',
                        'EMPLOYEE' => 'Transport d\'employés',
                        'STUDENT' => 'Transport scolaire / étudiant',
                        'RECURRING' => 'Trajet régulier programmé',
                        'OTHER' => 'Autre',
                    ]),
                ],
                'tips' => null,
            ],
            [
                'key' => 'route',
                'title' => 'Trajet',
                'objective' => 'Obtenir un trajet précis : départ, destination et horaires.',
                'script' => 'Parfait. Parlons maintenant du trajet.',
                'question' => 'D\'où partez-vous et où souhaitez-vous aller ?',
                'prompts' => [
                    'trip_type' => 'C\'est plutôt un aller simple ou un aller-retour ?',
                    'departure_time' => 'À quelle heure souhaitez-vous partir ?',
                    'return_time' => 'Et pour le retour, à quelle heure environ ?',
                ],
                'options' => [
                    'trip_type' => self::opts(['ONE_WAY' => 'Aller simple', 'ROUND_TRIP' => 'Aller-retour']),
                ],
                'tips' => 'Faites préciser le quartier ou un point de repère, pas seulement la ville.',
            ],
            [
                'key' => 'schedule',
                'title' => 'Fréquence',
                'objective' => 'Savoir si le besoin est ponctuel ou récurrent.',
                'script' => null,
                'question' => 'À quelle fréquence avez-vous besoin de ce trajet ?',
                'prompts' => [
                    'days_of_week' => 'Quels jours de la semaine ?',
                    'trips_per_week' => 'Combien de trajets par semaine environ ?',
                    'is_recurring' => 'Est-ce un besoin régulier dans le temps ?',
                ],
                'options' => [
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
                'tips' => null,
            ],
            [
                'key' => 'passengers',
                'title' => 'Passagers',
                'objective' => 'Connaître le nombre de personnes à transporter.',
                'script' => null,
                'question' => 'Combien de personnes voyageront ?',
                'prompts' => [
                    'total_employees' => 'Combien d\'employés au total sont concernés ?',
                    'estimated_passengers_per_trip' => 'Combien de passagers par trajet en moyenne ?',
                ],
                'options' => [],
                'tips' => null,
            ],
            [
                'key' => 'shared',
                'title' => 'Transport partagé',
                'objective' => 'Présenter positivement le transport partagé.',
                'script' => "Une des solutions que nous pouvons proposer, lorsque le trajet s'y prête, est le transport partagé. Cela permet de réduire le coût du déplacement tout en gardant un service organisé et programmé.\n\nLes trajets partagés sont organisés uniquement entre personnes ayant des itinéraires et des horaires compatibles : vous gardez un service ponctuel, confortable et sans détour inutile.",
                'question' => 'Est-ce que vous seriez ouvert à partager le trajet avec une autre personne ayant un trajet compatible ?',
                'prompts' => ['shared_direction' => 'Plutôt à l\'aller, au retour, ou dans les deux sens ?'],
                'options' => [
                    'shared_transport' => self::opts(['YES' => 'Oui', 'MAYBE' => 'Peut-être', 'NO' => 'Non']),
                    'shared_direction' => self::opts(['OUTBOUND' => 'Aller', 'RETURN' => 'Retour', 'BOTH' => 'Les deux']),
                ],
                'tips' => 'Si le client hésite, rassurez-le : horaires garantis, trajets compatibles uniquement, chauffeurs professionnels.',
            ],
            [
                'key' => 'experience',
                'title' => 'Expérience MiralDrive',
                'objective' => 'Savoir si le client connaît déjà MiralDrive.',
                'script' => null,
                'question' => 'Avez-vous déjà utilisé MiralDrive auparavant ?',
                'prompts' => [
                    'experience_rating' => 'Sur 5, comment évalueriez-vous votre expérience ?',
                    'experience_feedback' => 'Qu\'avez-vous apprécié ?',
                    'improvement_request' => 'Qu\'est-ce que nous pourrions améliorer ?',
                ],
                'options' => [
                    'used_miraldrive' => self::opts(['YES' => 'Oui', 'NO' => 'Non', 'UNKNOWN' => 'Ne sait pas']),
                ],
                'tips' => null,
            ],
            [
                'key' => 'current_solution',
                'title' => 'Solution actuelle',
                'objective' => 'Identifier la solution actuelle et ses points de friction.',
                'script' => null,
                'question' => 'Est-ce que vous utilisez actuellement un autre service pour ces trajets ?',
                'prompts' => [
                    'current_provider_details' => 'Précisions (nom du service, coût actuel...)',
                    'customer_preference' => 'Qu\'est-ce qui vous plaît le plus dans votre solution actuelle ?',
                    'pain_point' => 'Qu\'est-ce qui vous pose le plus problème ?',
                ],
                'options' => [
                    'current_provider' => self::opts([
                        'NO' => 'Aucun service',
                        'TRADITIONAL_TAXI' => 'Taxi traditionnel',
                        'APPLICATION' => 'Application (VTC)',
                        'PRIVATE_TRANSPORT' => 'Transport privé / véhicule personnel',
                        'TRANSPORT_COMPANY' => 'Société de transport',
                        'PRIVATE_DRIVER' => 'Chauffeur privé',
                        'OTHER' => 'Autre',
                    ]),
                ],
                'tips' => 'Le point de douleur est la clé de la proposition commerciale : faites-le reformuler.',
            ],
            [
                'key' => 'b2b',
                'title' => 'Qualification entreprise (B2B)',
                'objective' => 'Qualifier le besoin entreprise et le rôle de l\'interlocuteur.',
                'script' => 'Pour vous proposer une solution adaptée à votre entreprise, j\'ai quelques questions rapides.',
                'question' => 'Est-ce que vous êtes la personne qui décide de ce type de service dans votre entreprise ?',
                'prompts' => [
                    'company_name' => 'Quel est le nom de votre entreprise ?',
                    'company_size' => 'Combien de salariés compte l\'entreprise ?',
                    'employees_concerned' => 'Combien d\'employés seraient concernés par le transport ?',
                    'trips_per_day' => 'Combien de trajets par jour faudrait-il prévoir ?',
                    'decision_maker_name' => 'Nom du décideur (si ce n\'est pas l\'interlocuteur)',
                ],
                'options' => [
                    'decision_role' => self::opts([
                        'DECISION_MAKER' => 'Oui, je suis décideur',
                        'INFLUENCER' => 'Je participe à la décision',
                        'NEEDS_APPROVAL' => 'Je dois obtenir une validation',
                    ]),
                ],
                'tips' => 'Étape affichée uniquement pour un besoin entreprise / employés.',
            ],
            [
                'key' => 'priorities',
                'title' => 'Priorités du client',
                'objective' => 'Identifier le critère de choix principal (sans commencer par le prix).',
                'script' => null,
                'question' => 'Aujourd\'hui, qu\'est-ce qui est le plus important pour vous ?',
                'prompts' => [],
                'options' => [
                    'main_priority' => self::opts([
                        'PRICE' => 'Le prix',
                        'PUNCTUALITY' => 'La ponctualité',
                        'COMFORT' => 'Le confort',
                        'RELIABILITY' => 'La fiabilité',
                        'ORGANIZATION' => 'L\'organisation',
                    ]),
                ],
                'tips' => 'Ne proposez pas les options : laissez le client répondre librement, puis sélectionnez.',
            ],
            [
                'key' => 'qualification',
                'title' => 'Qualification',
                'objective' => 'Mesurer l\'intérêt et la priorité commerciale.',
                'script' => 'Merci beaucoup pour toutes ces informations, c\'est très clair.',
                'question' => 'Souhaitez-vous recevoir une proposition adaptée à votre besoin ?',
                'prompts' => [
                    'wants_quotation' => 'Le client demande une proposition / un devis',
                    'wants_callback' => 'Le client souhaite être rappelé par un conseiller',
                    'priority_stars' => 'Votre évaluation de la priorité commerciale',
                ],
                'options' => [],
                'tips' => 'Le score d\'intérêt est calculé automatiquement. Les étoiles reflètent votre ressenti.',
            ],
            [
                'key' => 'closing',
                'title' => 'Clôture de l\'appel',
                'objective' => 'Conclure positivement et définir la prochaine action.',
                'script' => 'Je vous remercie pour votre temps. Nous revenons vers vous très rapidement avec une solution adaptée. Très bonne journée !',
                'question' => null,
                'prompts' => [
                    'summary_note' => 'Résumé interne de l\'appel (trajet, horaires, fréquence, passagers, partage, solution actuelle, motivation)',
                    'next_action' => 'Prochaine action',
                    'callback_at' => 'Date et heure du rappel',
                ],
                'options' => [
                    'next_action' => self::opts([
                        'QUALIFIED' => 'Qualifié',
                        'CALLBACK' => 'Rappeler',
                        'SEND_QUOTATION' => 'Envoyer un devis',
                        'TRANSFER_TO_SALES' => 'Transférer au commercial',
                        'FOLLOW_UP' => 'Relance / suivi',
                        'NOT_INTERESTED' => 'Pas intéressé',
                        'NRP' => 'NRP (pas de réponse)',
                    ]),
                ],
                'tips' => 'Exemple : « Client recherche transport quotidien Sfax → centre-ville, départ 07h30, retour 17h30, 5 jours/semaine. 2 personnes. Ouvert au transport partagé. »',
            ],
            [
                'key' => 'summary',
                'title' => 'Récapitulatif',
                'objective' => 'Vérifier les informations avant de clôturer le lead.',
                'script' => null,
                'question' => null,
                'prompts' => [],
                'options' => [],
                'tips' => 'Relisez le récapitulatif. Cliquez sur une section pour la corriger avant de valider.',
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
     * @param  array<string, string>  $labels
     * @return array<int, array{value: string, label: string}>
     */
    private static function opts(array $labels): array
    {
        return collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
