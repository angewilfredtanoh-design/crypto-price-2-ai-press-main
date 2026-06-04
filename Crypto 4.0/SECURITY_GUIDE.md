# 🔐 GUIDE DE SÉCURITÉ - CONFIGURATION DES CLÉS API

## ⚠️ URGENT : Clé API Exposée Corrigée

La clé API Mistral qui était hardcodée dans `config.php` a été supprimée et remplacée par un système sécurisé de variables d'environnement.

---

## 📋 NOUVELLE PROCÉDURE DE CONFIGURATION

### Étape 1 : Créer le fichier `.env`

Depuis le dossier `Crypto 4.0/` :

```bash
cp .env.example .env
```

### Étape 2 : Éditer le fichier `.env`

Ouvrez `.env` et remplacez les valeurs placeholder :

```bash
# Pour une seule clé API (recommandé)
MISTRAL_API_KEY=votre_vraie_cle_api_mistral_ici

# Pour plusieurs clés (rotation automatique)
# MISTRAL_API_KEYS=cle_1,cle_2,cle_3

# Définir l'environnement
APP_ENV=production  # ou 'development' pour les tests
```

### Étape 3 : Vérifier les permissions

Assurez-vous que le fichier `.env` n'est pas lisible publiquement :

```bash
chmod 600 .env
chown www-data:www-data .env  # Sur serveur web
```

---

## 🔒 MESURES DE SÉCURITÉ IMPLÉMENTÉES

### 1. Fichier `.env` ignoré par Git
Le fichier `.gitignore` a été mis à jour pour exclure :
- `.env`
- `.env.local`
- `.env.production`
- `.env.*.local`

### 2. Chargement automatique des variables
Le fichier `config.php` charge automatiquement :
- Les variables depuis `.env` s'il existe
- Les variables d'environnement système (`getenv()`)
- Fallback sécurisé en développement uniquement

### 3. Protection Production
En mode `APP_ENV=production`, une exception est levée si aucune clé API n'est configurée.

---

## 🧪 TESTER LA CONFIGURATION

### Test en ligne de commande :

```bash
cd "Crypto 4.0"
export MISTRAL_API_KEY=votre_cle_test
php -r "require 'config.php'; print_r(DEFAULT_MISTRAL_API_KEYS);"
```

### Test avec fichier `.env` :

```bash
cd "Crypto 4.0"
echo "MISTRAL_API_KEY=test123" > .env
php -r "require 'config.php'; print_r(DEFAULT_MISTRAL_API_KEYS);"
```

Résultat attendu :
```
Array
(
    [0] => test123
)
```

---

## 🚀 MIGRATION DEPUIS L'ANCIENNE CONFIGURATION

### Avant (❌ Non sécurisé) :
```php
define('DEFAULT_MISTRAL_API_KEYS', [
    'nrcTwO2J9Y09I04vgFWEVVtjg4iT7aya'  // Clé exposée !
]);
```

### Après (✅ Sécurisé) :
```bash
# Dans .env
MISTRAL_API_KEY=votre_cle_securisee
```

```php
// config.php charge automatiquement depuis .env
$mistralApiKeys = [getenv('MISTRAL_API_KEY')];
define('DEFAULT_MISTRAL_API_KEYS', $mistralApiKeys);
```

---

## 📝 BONNES PRATIQUES

### ✅ À FAIRE :
- Utiliser des variables d'environnement pour TOUTES les clés API
- Mettre `APP_ENV=production` en production
- Restreindre les permissions du fichier `.env` (chmod 600)
- Utiliser un gestionnaire de secrets en entreprise (Vault, AWS Secrets Manager)
- Rotation régulière des clés API

### ❌ À NE PAS FAIRE :
- Committer le fichier `.env` dans Git
- Hardcoder des clés API dans le code source
- Partager les clés API par email/chat
- Utiliser la même clé sur tous les environnements
- Laisser des clés expirées ou inutilisées

---

## 🔄 ROTATION DES CLÉS API

Si vous suspectez une compromission :

1. **Générer une nouvelle clé** sur https://console.mistral.ai/
2. **Mettre à jour `.env`** immédiatement
3. **Redémarrer** le service PHP/Apache
4. **Révoquer l'ancienne clé** dans la console Mistral
5. **Vérifier les logs** d'utilisation API

---

## 🛡️ VÉRIFICATION DE SÉCURITÉ

Exécutez ce script pour vérifier votre configuration :

```bash
cd "Crypto 4.0"
php -r "
require 'config.php';
\$keys = DEFAULT_MISTRAL_API_KEYS;
if (empty(\$keys) || \$keys[0] === 'test_key_placeholder') {
    echo \"⚠️  WARNING: Aucune clé API configurée!\n\";
    exit(1);
}
if (\$keys[0] === 'nrcTwO2J9Y09I04vgFWEVVtjg4iT7aya') {
    echo \"🚨 CRITICAL: Ancienne clé exposée détectée!\n\";
    exit(1);
}
echo \"✅ Configuration sécurisée validée.\n\";
echo \"Nombre de clés: \" . count(\$keys) . \"\n\";
"
```

---

## 📞 SUPPORT

En cas de problème :
1. Vérifiez que `.env` existe et contient `MISTRAL_API_KEY`
2. Vérifiez les permissions du fichier
3. Consultez les logs : `logs/error.log`
4. Testez en CLI avant déploiement web

---

**Document créé suite à l'audit de sécurité - Priorité 1**
*Date : $(date +%Y-%m-%d)*
