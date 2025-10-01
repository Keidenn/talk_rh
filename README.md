# Talk RH – Demande de congés (Nextcloud app)

Une application Nextcloud simple pour gérer les demandes de congés.

Fonctionnalités:
- Vue employé: créer, lister et supprimer ses demandes (suppression possible uniquement si en statut "pending").
- Vue admin: approuver ou refuser les demandes, avec commentaire optionnel.
- Paramètre admin: choisir le groupe dont les membres accèdent à la vue admin (par défaut: groupe Nextcloud `admin`). Les administrateurs Nextcloud sont toujours autorisés.

## Schéma BDD
Table `oc_talk_rh_leaves` (préfixe `oc_` variable):
- id (int, PK, AI)
- uid (string)
- start_date (string)
- end_date (string)
- type (string)
- status (pending|approved|rejected)
- reason (text)
- admin_comment (text)
- created_at (string)
- updated_at (string)

## Développement
- Controllers sous `lib/Controller/`
- Service métier `lib/Service/LeaveService.php`
- Migration `lib/Migration/Version0001Date20250919.php`
- Templates `templates/`

## Sécurité
- Toutes les routes nécessitent un utilisateur connecté.
- Les routes admin vérifient que l'utilisateur est admin Nextcloud OU membre du groupe défini dans les réglages de l'app.

## Compatibilité
- Notifications : Envoie de notifications via l'app Notifications
- Talk : Demandes & retour envoyées sur Talk au supérieur défini dans les paramètres utilisateurs de nextcloud ainsi que dans un canal multi défini dans les paramètres de talk_rh
- Calendar : Création automatique sur l'agenda des demandes validées
