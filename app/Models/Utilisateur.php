<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Utilisateur
{
    /** Retourne l'utilisateur actif correspondant à l'e-mail (avec le code de son rôle). */
    public static function trouverParEmail(string $email): ?array
    {
        $sql = 'SELECT u.*, r.code AS role
                FROM utilisateurs u
                JOIN roles r ON r.id = u.role_id
                WHERE u.email = :email AND u.actif = 1
                LIMIT 1';

        $requete = Database::getConnection()->prepare($sql);
        $requete->execute(['email' => $email]);
        $ligne = $requete->fetch();

        return $ligne ?: null;
    }

    /** Techniciens actifs d'un domaine, avec leur charge actuelle (tickets en cours). */
    public static function techniciensDuDomaine(int $domaineId): array
    {
        $requete = Database::getConnection()->prepare(
                        'SELECT u.id, u.prenom, u.nom, u.email,
                    (SELECT COUNT(*) FROM tickets t
                      WHERE t.technicien_id = u.id
                        AND t.statut IN (\'assigne\', \'en_cours\', \'en_attente\')) AS charge
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE r.code = \'technicien\' AND u.actif = 1 AND u.domaine_id = :d
             ORDER BY u.nom, u.prenom'
        );
        $requete->execute(['d' => $domaineId]);
        return $requete->fetchAll();
        
    }



    /** Vérifie qu'un utilisateur est bien un technicien actif de ce domaine. */
    public static function trouverTechnicienDuDomaine(int $id, int $domaineId): ?array
    {
        foreach (self::techniciensDuDomaine($domaineId) as $technicien) {
            if ((int) $technicien['id'] === $id) {
                return $technicien;
            }
        }
        return null;
    }

        /** Administrateurs actifs (destinataires des alertes « ticket non assigné »). */
    public static function administrateurs(): array
    {
        return Database::getConnection()->query(
            "SELECT u.id, u.prenom, u.nom, u.email
             FROM utilisateurs u JOIN roles r ON r.id = u.role_id
             WHERE r.code = 'admin' AND u.actif = 1"
        )->fetchAll();
    }

        // ---------- Gestion des comptes (administration) ----------

    public static function trouverParId(int $id): ?array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT u.*, r.code AS role, r.libelle AS role_libelle, d.libelle AS domaine
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN domaines d ON d.id = u.domaine_id
             WHERE u.id = :id'
        );
        $requete->execute(['id' => $id]);
        return $requete->fetch() ?: null;
    }

    /** Tous les comptes, avec leur rôle, leur domaine et le nombre de tickets en cours (techniciens). */
    public static function tous(): array
    {
        return Database::getConnection()->query(
            "SELECT u.id, u.nom, u.prenom, u.email, u.service, u.actif, u.statut_compte, u.cree_le,
                    r.code AS role, r.libelle AS role_libelle, d.libelle AS domaine,
                    (SELECT COUNT(*) FROM tickets t
                      WHERE t.technicien_id = u.id
                        AND t.statut IN ('assigne', 'en_cours', 'en_attente')) AS tickets_en_cours
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN domaines d ON d.id = u.domaine_id
             ORDER BY u.actif DESC, r.id DESC, u.nom, u.prenom"
        )->fetchAll();
    }

    public static function roles(): array
    {
        return Database::getConnection()->query('SELECT id, code, libelle FROM roles ORDER BY id')->fetchAll();
    }

    public static function domaines(): array
    {
        return Database::getConnection()->query('SELECT id, code, libelle FROM domaines ORDER BY id')->fetchAll();
    }

    /** L'e-mail est-il déjà pris (par un autre compte que $sauf) ? */
    public static function emailExiste(string $email, ?int $sauf = null): bool
    {
        $requete = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM utilisateurs WHERE email = :email AND id <> :sauf'
        );
        $requete->execute(['email' => $email, 'sauf' => $sauf ?? 0]);
        return (int) $requete->fetchColumn() > 0;
    }

    public static function creer(array $d, string $hash): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, domaine_id, service, actif)
             VALUES (:nom, :prenom, :email, :mdp, :role, :domaine, :service, 1)'
        )->execute([
            'nom' => $d['nom'], 'prenom' => $d['prenom'], 'email' => $d['email'], 'mdp' => $hash,
            'role' => $d['role_id'], 'domaine' => $d['domaine_id'], 'service' => $d['service'],
        ]);
        return (int) $pdo->lastInsertId();
    }

        public static function modifier(int $id, array $d, bool $actif): void
    {
        Database::getConnection()->prepare(
            'UPDATE utilisateurs
             SET nom = :nom, prenom = :prenom, email = :email, role_id = :role,
                 domaine_id = :domaine, service = :service, actif = :actif,
                 statut_compte = IF(:actif2 = 1, \'actif\', statut_compte)
             WHERE id = :id'
        )->execute([
            'nom' => $d['nom'], 'prenom' => $d['prenom'], 'email' => $d['email'], 'role' => $d['role_id'],
            'domaine' => $d['domaine_id'], 'service' => $d['service'], 'actif' => $actif ? 1 : 0,
            'actif2' => $actif ? 1 : 0, 'id' => $id,
        ]);
    }

    public static function changerMotDePasse(int $id, string $hash): void
    {
        Database::getConnection()
            ->prepare('UPDATE utilisateurs SET mot_de_passe = :mdp WHERE id = :id')
            ->execute(['mdp' => $hash, 'id' => $id]);
    }

    public static function compterAdminsActifs(): int
    {
        return (int) Database::getConnection()->query(
            "SELECT COUNT(*) FROM utilisateurs u JOIN roles r ON r.id = u.role_id
             WHERE r.code = 'admin' AND u.actif = 1"
        )->fetchColumn();
    }

    /** Tickets qu'un technicien est en train de traiter (assignés, en cours ou en attente). */
    public static function ticketsEnCours(int $technicienId): int
    {
        $requete = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM tickets
             WHERE technicien_id = :id AND statut IN ('assigne', 'en_cours', 'en_attente')"
        );
        $requete->execute(['id' => $technicienId]);
        return (int) $requete->fetchColumn();
    }

    public static function aDesTickets(int $id): bool
    {
        $requete = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM tickets WHERE demandeur_id = :demandeur OR technicien_id = :technicien'
        );
        $requete->execute(['demandeur' => $id, 'technicien' => $id]);
        return (int) $requete->fetchColumn() > 0;
    }

    public static function supprimer(int $id): bool
    {
        $requete = Database::getConnection()->prepare('DELETE FROM utilisateurs WHERE id = :id');
        $requete->execute(['id' => $id]);
        return $requete->rowCount() > 0;
    }

     public static function trouverParEmailTous(string $email): ?array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT u.*, r.code AS role FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE u.email = :email LIMIT 1'
        );
        $requete->execute(['email' => $email]);
        return $requete->fetch() ?: null;
    }

   
        /** Crée (ou met à jour, si l'e-mail n'a jamais été confirmé) une demande d'inscription. */
    public static function enregistrerInscription(?int $idExistant, array $d, string $hashMdp, string $hashCode, string $expire): int
    {
        $pdo = Database::getConnection();
        $parametres = [
            'nom' => $d['nom'], 'prenom' => $d['prenom'], 'email' => $d['email'], 'mdp' => $hashMdp,
            'role' => $d['role_id'], 'domaine' => $d['domaine_id'], 'service' => $d['service'],
            'code' => $hashCode, 'expire' => $expire,
        ];
        if ($idExistant === null) {
            $pdo->prepare(
                "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id, domaine_id, service, actif,
                                           statut_compte, jeton_hash, jeton_expire_le, code_essais)
                 VALUES (:nom, :prenom, :email, :mdp, :role, :domaine, :service, 0, 'email_a_verifier', :code, :expire, 0)"
            )->execute($parametres);
            return (int) $pdo->lastInsertId();
        }
        $parametres['id'] = $idExistant;
        $pdo->prepare(
            "UPDATE utilisateurs
             SET nom = :nom, prenom = :prenom, mot_de_passe = :mdp, role_id = :role, domaine_id = :domaine,
                 service = :service, jeton_hash = :code, jeton_expire_le = :expire, code_essais = 0
             WHERE id = :id AND statut_compte = 'email_a_verifier' AND email = :email"
        )->execute($parametres);
        return $idExistant;
    }

    /** Compte dont l'adresse attend encore sa confirmation par code. */
    public static function trouverEnVerification(string $email): ?array
    {
        $requete = Database::getConnection()->prepare(
            "SELECT u.*, r.code AS role, d.libelle AS domaine
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN domaines d ON d.id = u.domaine_id
             WHERE u.email = :email AND u.statut_compte = 'email_a_verifier'
             LIMIT 1"
        );
        $requete->execute(['email' => $email]);
        return $requete->fetch() ?: null;
    }

    public static function ajouterEssai(int $id): void
    {
        Database::getConnection()->prepare('UPDATE utilisateurs SET code_essais = code_essais + 1 WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /** Remplace le code de vérification (nouvel envoi) et remet le compteur d'essais à zéro. */
    public static function remplacerCode(int $id, string $hashCode, string $expire): void
    {
        Database::getConnection()->prepare(
            'UPDATE utilisateurs SET jeton_hash = :code, jeton_expire_le = :expire, code_essais = 0 WHERE id = :id'
        )->execute(['code' => $hashCode, 'expire' => $expire, 'id' => $id]);
    }

    public static function confirmerEmail(int $id): void
    {
        Database::getConnection()->prepare(
            "UPDATE utilisateurs SET statut_compte = 'en_attente', jeton_hash = NULL, jeton_expire_le = NULL, code_essais = 0 WHERE id = :id"
        )->execute(['id' => $id]);
    }

     public static function demandesEnAttente(): array
    {
        return Database::getConnection()->query(
            "SELECT u.id, u.nom, u.prenom, u.email, u.service, u.cree_le,
                    r.code AS role, r.libelle AS role_libelle, d.libelle AS domaine
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN domaines d ON d.id = u.domaine_id
             WHERE u.statut_compte = 'en_attente'
             ORDER BY u.cree_le"
        )->fetchAll();   
    }

    
    public static function compterEnAttente(): int
    {
        return (int) Database::getConnection()
            ->query("SELECT COUNT(*) FROM utilisateurs WHERE statut_compte = 'en_attente'")->fetchColumn();
    }

    public static function decider(int $id, bool $approuve): void
    {
        Database::getConnection()->prepare(
            "UPDATE utilisateurs SET statut_compte = :statut, actif = :actif WHERE id = :id AND statut_compte = 'en_attente'"
        )->execute(['statut' => $approuve ? 'actif' : 'refuse', 'actif' => $approuve ? 1 : 0, 'id' => $id]);
    }

    /**
     * Technicien actif du domaine qui a le moins de tickets en cours.
     * À charge égale : celui qui n'a jamais reçu de ticket, puis celui qui a reçu le plus anciennement.
     */
    public static function moinsChargeDuDomaine(int $domaineId): ?array
    {
        $requete = Database::getConnection()->prepare(
            'SELECT u.id, u.prenom, u.nom, u.email,
                    (SELECT COUNT(*) FROM tickets t
                      WHERE t.technicien_id = u.id
                        AND t.statut IN (\'assigne\', \'en_cours\', \'en_attente\')) AS charge,
                    (SELECT MAX(t.assigne_le) FROM tickets t
                      WHERE t.technicien_id = u.id) AS dernier_assigne
             FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE r.code = \'technicien\' AND u.actif = 1 AND u.domaine_id = :domaine
             ORDER BY charge ASC,
                      (dernier_assigne IS NOT NULL) ASC,
                      dernier_assigne ASC,
                      u.id ASC
             LIMIT 1'
        );
        $requete->execute(['domaine' => $domaineId]);
        return $requete->fetch() ?: null;
    }


}