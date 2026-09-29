<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Sécurité : uniquement accessible par l'administrateur connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Identifiant de devis invalide.");
}

// Récupération de la demande de devis
try {
    $stmt = $pdo->prepare("SELECT * FROM `devis` WHERE id = ?");
    $stmt->execute([$id]);
    $devis = $stmt->fetch();
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

if (!$devis) {
    die("Demande de devis introuvable.");
}

// Récupération des informations de l'entreprise
try {
    $company = $pdo->query("SELECT * FROM `company_settings` LIMIT 1")->fetch();
} catch (PDOException $e) {
    $company = null;
}

// Mappage des types de projets
$project_labels = [
    'peinture-int' => 'Peinture Intérieure',
    'revetement-mur' => 'Revêtement Mural',
    'ravalement-ext' => 'Peinture Extérieure',
    'revetement-sol' => 'Revêtement de Sol',
    'autre' => 'Autre / Plusieurs'
];
$project_name = isset($project_labels[$devis['project_type']]) ? $project_labels[$devis['project_type']] : $devis['project_type'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis_<?php echo $devis['id']; ?>_<?php echo htmlspecialchars($devis['lastname'], ENT_QUOTES, 'UTF-8'); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1a1a1a;
            --accent-color: #c5a880; /* Bronze/Gold */
            --text-dark: #2d3748;
            --text-light: #718096;
            --border-color: #e2e8f0;
            --bg-light: #f7fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            line-height: 1.5;
            background-color: #f0f2f5;
            padding: 40px 20px;
        }

        .pdf-container {
            max-width: 800px;
            background: #ffffff;
            margin: 0 auto;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            position: relative;
        }

        /* Action Bar (Top) */
        .action-bar {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .action-bar h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-print {
            background-color: var(--primary-color);
            color: #ffffff;
        }

        .btn-print:hover {
            background-color: #000;
        }

        .btn-close {
            background-color: #fff;
            color: var(--text-dark);
            border-color: var(--border-color);
        }

        .btn-close:hover {
            background-color: var(--bg-light);
        }

        /* Header Document */
        .pdf-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 30px;
            margin-bottom: 30px;
        }

        .company-logo-block {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .company-logo {
            max-height: 55px;
            width: auto;
            align-self: flex-start;
        }

        .company-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: -0.02em;
        }

        .company-details {
            font-size: 0.85rem;
            color: var(--text-light);
            line-height: 1.4;
        }

        .document-details {
            text-align: right;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .doc-title-badge {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 10px;
        }

        .doc-meta {
            font-size: 0.9rem;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .doc-meta span {
            color: var(--text-light);
        }

        /* Party Section (From/To) */
        .parties-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }

        .party-card {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 6px;
        }

        .party-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--accent-color);
            margin-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 6px;
        }

        .party-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .party-info-row {
            font-size: 0.85rem;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .party-info-row span {
            color: var(--text-light);
        }

        /* Project Specs Table */
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .specs-table th {
            background-color: var(--primary-color);
            color: #ffffff;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
        }

        .specs-table th:first-child {
            border-top-left-radius: 4px;
            border-bottom-left-radius: 4px;
        }

        .specs-table th:last-child {
            border-top-right-radius: 4px;
            border-bottom-right-radius: 4px;
        }

        .specs-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        /* Project Description Box */
        .description-section {
            margin-bottom: 40px;
        }

        .description-title {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary-color);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .description-box {
            background-color: var(--bg-light);
            border: 1px dashed var(--accent-color);
            padding: 20px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: var(--text-dark);
            white-space: pre-line;
            min-height: 120px;
        }

        /* Signatures Block */
        .signatures-block {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding-top: 20px;
        }

        .signature-box {
            border-top: 1px dashed var(--border-color);
            padding-top: 15px;
            font-size: 0.8rem;
            color: var(--text-light);
            line-height: 1.4;
        }

        .signature-box .title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 45px;
        }

        .signature-box .stamp-area {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed var(--border-color);
            border-radius: 4px;
            color: var(--border-color);
            font-style: italic;
        }

        /* Print Media styling */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }

            .pdf-container {
                box-shadow: none;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }
            
            @page {
                size: A4;
                margin: 20mm 15mm;
            }
        }
    </style>
</head>
<body>

    <!-- Bar d'actions (Invisible à l'impression) -->
    <div class="action-bar no-print">
        <h2>Visualisation de la demande de devis #<?php echo $devis['id']; ?></h2>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-print">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Imprimer ou Enregistrer en PDF
            </button>
            <a href="admin.php" class="btn btn-close">Fermer</a>
        </div>
    </div>

    <!-- Le Document PDF -->
    <div class="pdf-container">
        
        <!-- En-tête -->
        <header class="pdf-header">
            <div class="company-logo-block">
                <?php if (file_exists(__DIR__ . '/assets/images/head-logo.png')): ?>
                    <img src="assets/images/head-logo.png" alt="Cetin Family Logo" class="company-logo">
                <?php else: ?>
                    <span class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'Cetin Family', ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                
                <div class="company-details">
                    <p><?php echo htmlspecialchars($company['address'] ?? 'Adresse non configurée', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><?php echo htmlspecialchars(($company['postal_code'] ?? '') . ' ' . ($company['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Tél : <?php echo htmlspecialchars($company['phone'] ?? 'Non disponible', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Email : <?php echo htmlspecialchars($company['email'] ?? 'Non disponible', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="document-details">
                <div class="doc-title-badge">FICHE DE DEVIS</div>
                <div class="doc-meta">
                    <p><strong>Numéro :</strong> #<?php echo str_pad($devis['id'], 6, '0', STR_PAD_LEFT); ?></p>
                    <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($devis['created_at'])); ?></p>
                    <p><strong>Heure :</strong> <?php echo date('H:i', strtotime($devis['created_at'])); ?></p>
                    <p><strong>Statut :</strong> <span><?php 
                        $status_map = ['en_attente' => 'En attente', 'en_cours' => 'En cours', 'traite' => 'Traité', 'rejete' => 'Rejeté'];
                        echo $status_map[$devis['status']] ?? $devis['status'];
                    ?></span></p>
                </div>
            </div>
        </header>

        <!-- Parties concernées -->
        <section class="parties-grid">
            <div class="party-card">
                <div class="party-title">Émetteur</div>
                <div class="party-name"><?php echo htmlspecialchars($company['company_name'] ?? 'Cetin Family', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="party-info-row"><span>Région :</span> Mâcon, Saône-et-Loire (71)</div>
                <div class="party-info-row"><span>Activité :</span> Rénovation &amp; Peinture</div>
                <div class="party-info-row"><span>Rayon :</span> <?php echo (int)($company['radius_km'] ?? 40); ?> km</div>
            </div>

            <div class="party-card">
                <div class="party-title">Client / Destinataire</div>
                <div class="party-name"><?php echo htmlspecialchars($devis['firstname'] . ' ' . $devis['lastname'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="party-info-row"><span>Adresse chantier :</span> <?php echo htmlspecialchars($devis['location'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="party-info-row"><span>Téléphone :</span> <?php echo htmlspecialchars($devis['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="party-info-row"><span>E-mail :</span> <?php echo htmlspecialchars($devis['email'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </section>

        <!-- Tableau des spécifications du projet -->
        <table class="specs-table">
            <thead>
                <tr>
                    <th>Description du projet</th>
                    <th style="text-align: center; width: 150px;">Surface</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span style="color: var(--text-light); font-size: 0.8rem;">Type de travaux demandé par le client</span>
                    </td>
                    <td style="text-align: center;">
                        <strong><?php echo (int)$devis['surface']; ?> m²</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Message/Description détaillée -->
        <section class="description-section">
            <div class="description-title">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:middle;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                Description détaillée du projet par le client
            </div>
            <div class="description-box">
                <?php echo htmlspecialchars($devis['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </section>

        <!-- Cadre signature -->
        <footer class="signatures-block">
            <div class="signature-box">
                <div class="title">Pour le Client (Bon pour accord)</div>
                <p style="margin-bottom: 8px;">Date : ____/____/________</p>
                <p>Signature (précédée de la mention "bon pour accord") :</p>
            </div>
            
            <div class="signature-box">
                <div class="title">Pour l'Entreprise (<?php echo htmlspecialchars($company['company_name'] ?? 'Cetin Family', ENT_QUOTES, 'UTF-8'); ?>)</div>
                <div class="stamp-area">Cachet commercial</div>
            </div>
        </footer>

    </div>

</body>
</html>
