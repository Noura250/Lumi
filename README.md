# ✨ Lumi — Application de messagerie web

> Un projet qui me tient particulièrement à cœur et dont je suis aujourd'hui la plus fière.

## 🌙 À propos de Lumi

Lumi est une application de messagerie web que j'ai développée dans le cadre de mon apprentissage en informatique.

L'idée était de créer une véritable petite plateforme de discussion, et pas simplement une page permettant d'envoyer des messages.

Au fur et à mesure du développement, Lumi a évolué pour intégrer plusieurs fonctionnalités : création de salons, conversations privées, système d'amis, notifications, gestion des utilisateurs et modération.

Ce projet est probablement celui dont je suis le plus fière, parce qu'il représente une vraie étape dans mon apprentissage.

Je suis partie de connaissances encore assez limitées en PHP et SQL, et j'ai progressivement appris à comprendre comment les différentes parties d'une application web peuvent fonctionner ensemble.

---

## 💬 Fonctionnalités

### 👤 Comptes utilisateurs
- Inscription
- Connexion / déconnexion
- Gestion des sessions
- Mots de passe protégés avec `password_hash()`
- Gestion des rôles utilisateur / administrateur

### 🏠 Salons de discussion
- Création de salons
- Salons publics
- Salons privés
- Ajout de membres aux salons privés
- Accès contrôlé aux salons privés
- Suppression des salons par un administrateur

### 💌 Messagerie privée
- Conversations entre deux utilisateurs
- Historique des messages
- Statut lu / non lu
- Notifications lors de la réception d'un message

### 👥 Système d'amis
- Envoyer une demande d'ami
- Accepter une demande
- Refuser une demande
- Supprimer un ami
- Voir sa liste d'amis
- Notifications liées aux demandes d'amis

### 🔔 Notifications
- Notifications pour les messages privés
- Notifications pour les demandes d'amis
- Notifications lors de l'acceptation d'une demande
- Marquage des notifications comme lues

### 🛡️ Sécurité
J'ai également travaillé sur la sécurisation de l'application avant de publier le projet sur GitHub :

- Requêtes SQL préparées avec PDO
- Mots de passe hashés
- Protection CSRF
- Sessions sécurisées
- Protection contre certains en-têtes HTTP indésirables
- Vérification des autorisations pour les salons privés
- Protection des informations de connexion à la base de données
- Fichier `.gitignore` pour éviter de publier les données sensibles

---

## 🛠️ Technologies utilisées

- **PHP**
- **MySQL**
- **HTML5**
- **CSS3**
- **PDO**
- **Git / GitHub**
- **MAMP**
- **PhpStorm**

---

## 📚 Ce que ce projet m'a appris

Lumi m'a permis de mieux comprendre des notions que je connaissais auparavant seulement de manière théorique.

J'ai notamment appris à travailler avec :

- les sessions PHP ;
- les requêtes SQL ;
- les relations entre plusieurs tables ;
- les clés étrangères et les index ;
- les requêtes préparées ;
- les formulaires PHP ;
- la gestion des droits utilisateurs ;
- la logique serveur ;
- la sécurité d'une application web ;
- Git et GitHub.

J'ai aussi appris quelque chose d'important : développer une application ne consiste pas seulement à faire fonctionner le code.

Il faut également réfléchir à la sécurité, à l'organisation du projet, à l'expérience utilisateur et à la manière dont les différentes fonctionnalités vont communiquer entre elles.

---

## 💡 Pourquoi Lumi est important pour moi

Lumi représente plus qu'un simple exercice.

C'est un projet dans lequel j'ai pu voir mon évolution en informatique.

Au début, certaines notions de PHP, SQL ou de sécurité me semblaient assez compliquées. Aujourd'hui, je suis capable de construire une application avec plusieurs fonctionnalités qui communiquent entre elles et de commencer à réfléchir à sa sécurité et à son organisation.

Je sais que Lumi est loin d'être une application professionnelle parfaite et qu'il reste encore beaucoup de choses que je pourrais améliorer.

Mais c'est justement ce qui me plaît dans ce projet.

Il représente ce que je suis capable de construire aujourd'hui, tout en me donnant une base pour continuer à progresser.

**C'est pour cette raison que Lumi est, à ce jour, le projet dont je suis la plus fière.**

---

## 🚀 Améliorations possibles

Le projet pourrait continuer à évoluer avec :

- une API ;
- une messagerie en temps réel avec WebSocket ;
- l'envoi de fichiers et d'images ;
- une meilleure gestion du profil utilisateur ;
- un système de recherche ;
- davantage de contrôles de sécurité ;
- une version mobile ;
- une interface d'administration plus complète.

---

## 👩🏽‍💻 Projet personnel

**Lumi** est un projet réalisé dans le cadre de mon apprentissage en informatique.

Il représente une étape importante dans mon parcours et me permet de mettre en pratique progressivement les compétences que j'acquiers en développement web, bases de données et sécurité.

Oui, il mes arrivé d' utilisé l'ia pour avancé plus vite dans le css :)
