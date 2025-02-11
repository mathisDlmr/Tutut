## Retour OAuth global
- uuid
- email
- firstName
- lastName
- type
- status
- active
- created_at
- updated_at
- deleted_at
- picture
- provider

## Champs pour une connexion CAS
- "type" => "utc-etu"
- "status" => "students"
- "active" => 1
- "deleted_at" => null
- "provider" => "cas"

## Champs pour une connexion mail
- "type" => "exte"
- "status" => "exte"
- "active" => 1
- "deleted_at" => null
- "provider" => "email"

## Extension des champs de l'OAuth
En réalité, les champs mentionné ci-dessus sont les champs users-infos
Le SIMDE propose aussi de recupérer 3 autres champs : 
- Les assos du semestre en cours : "read-assos"
- L'historique des assos : "read-assos-history"
- Les adhésions : "read-memberships"
Le setup se fait sur l'interface admin de l'OAuth [https://auth.assos.utc.fr/admin]
Attention, pour que l'OAuth fonctionne, il faut que le scopes des champs renvoyés par OAuth soit le même que ceux récup par le serveur (me demandez pas pourquoi, c'est le prez du SIMDE qui me l'a dit)