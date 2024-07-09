```markdown
# Scripts Linux

Ce dépôt contient divers scripts et configurations utiles pour la gestion des tâches et la surveillance du système sous Linux.

## Contenu du dépôt

### `task_manager.sh`
Ce script Bash permet de gérer une liste de tâches simple stockée dans un fichier Markdown. Il supporte les actions suivantes :
- **Ajouter une tâche** : Ajoute une nouvelle tâche à la liste.
- **Marquer une tâche comme terminée** : Marque une tâche spécifique comme terminée.
- **Lister les tâches** : Affiche toutes les tâches non terminées.

#### Utilisation
```bash
# Ajouter une tâche
./task_manager.sh add "Description de la tâche"

# Marquer une tâche comme terminée
./task_manager.sh complete <numéro_de_la_tâche>

# Lister les tâches
./task_manager.sh list
```
Le fichier des tâches est stocké dans `~/tasks.md`.

### `conkyrc.txt`
Ceci est un fichier de configuration pour Conky, un moniteur de système léger pour X. Il affiche diverses informations système telles que :
- Informations sur le système (nom, noyau, architecture)
- Uptime du système
- Utilisation du CPU et de la RAM
- Statistiques des processus
- Informations sur le système de fichiers
- Informations réseau (adresses IP, SSID, signal, vitesse)
- Ping de la box internet
- Appareils connectés au réseau local
- Les 5 premières tâches non terminées de `~/tasks.md`
- Les processus les plus gourmands en ressources

Pour utiliser ce fichier, placez-le dans votre répertoire de configuration Conky (`~/.config/conky/`) et lancez Conky.

### `ToggleVPN.desktop`
Ce fichier est un raccourci de bureau pour activer/désactiver un VPN. Il est conçu pour être utilisé avec un gestionnaire de réseau prenant en charge les connexions VPN via l'interface graphique.

#### Utilisation
1. Placez le fichier `.desktop` dans le répertoire des lanceurs d'applications (`~/.local/share/applications/`).
2. Assurez-vous que le fichier est exécutable :
   ```bash
   chmod +x ~/.local/share/applications/ToggleVPN.desktop
   ```
3. Utilisez votre environnement de bureau pour ajouter ce raccourci à votre barre de tâches ou menu d'applications.

## Sécurité
Assurez-vous de protéger vos clés SSH et autres informations sensibles. Ne partagez pas vos clés privées ou d'autres données personnelles dans ce dépôt.

## Contributeurs
Ce dépôt est maintenu par Cyril. Les contributions sont les bienvenues. Veuillez soumettre un pull request pour proposer des améliorations ou des ajouts.

```

Ce fichier `README.md` décrit brièvement chaque fichier et leur utilisation sans révéler d'informations personnelles. Si vous avez d'autres fichiers à inclure ou des modifications spécifiques à apporter, n'hésitez pas à me le faire savoir.
