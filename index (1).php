<?php
// Configuration SQLite
$dbFile = 'crypto_cache.db';
$cacheMinutes = 5; // Recharge après 5 minutes

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Création de la table si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS coins (
        id TEXT PRIMARY KEY,
        symbol TEXT,
        name TEXT,
        image TEXT,
        current_price REAL,
        market_cap REAL,
        market_cap_rank INTEGER,
        price_change_percentage_24h REAL,
        sparkline TEXT,
        last_update INTEGER
    )");
    
    // Vérifier si on doit rafraîchir les données
    $needRefresh = true;
    $stmt = $pdo->query("SELECT last_update FROM coins LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && (time() - $row['last_update']) < $cacheMinutes * 60) {
        $needRefresh = false;
    }
    
    if ($needRefresh) {
        // Appel API CoinGecko
        $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=eur&order=market_cap_desc&per_page=1000&page=1&sparkline=true";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                // Vider l'ancien cache
                $pdo->exec("DELETE FROM coins");
                
                // Insertion des nouvelles données
                $insert = $pdo->prepare("INSERT INTO coins (id, symbol, name, image, current_price, market_cap, market_cap_rank, price_change_percentage_24h, sparkline, last_update)
                                         VALUES (:id, :symbol, :name, :image, :price, :market_cap, :rank, :change24h, :sparkline, :last_update)");
                
                $now = time();
                foreach ($data as $coin) {
                    $sparklineJson = json_encode($coin['sparkline_in_7d']['price'] ?? []);
                    $insert->execute([
                        ':id' => $coin['id'],
                        ':symbol' => $coin['symbol'],
                        ':name' => $coin['name'],
                        ':image' => $coin['image'],
                        ':price' => $coin['current_price'],
                        ':market_cap' => $coin['market_cap'],
                        ':rank' => $coin['market_cap_rank'],
                        ':change24h' => $coin['price_change_percentage_24h'],
                        ':sparkline' => $sparklineJson,
                        ':last_update' => $now
                    ]);
                }
            }
        } else {
            echo "<div class='alert alert-danger'>Erreur API : HTTP $httpCode. Affichage du cache existant.</div>";
        }
    }
    
    // Récupérer toutes les cryptos depuis SQLite
    $query = "SELECT * FROM coins ORDER BY market_cap_rank ASC";
    $stmt = $pdo->query($query);
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Erreur base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Crypto - Toutes les données CoinGecko</title>
    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables pour le tri/recherche/pagination facile -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sparkline-canvas {
            width: 120px;
            height: 40px;
        }
        .coin-img {
            width: 32px;
            height: 32px;
        }
        .positive {
            color: #00b15d;
        }
        .negative {
            color: #e15241;
        }
        table.dataTable td {
            vertical-align: middle;
        }
        .badge-rank {
            font-size: 0.9rem;
        }
        footer {
            text-align: center;
            margin-top: 2rem;
            padding: 1rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-3">
        <h1 class="mb-2"><i class="bi bi-coin"></i> Toutes les cryptos (1000+)</h1>
        <p class="text-muted">Données actualisées toutes les <?= $cacheMinutes ?> minutes depuis CoinGecko API.<br>
        <strong>Sparkline = évolution 7 jours (prix en EUR).</strong></p>
        
        <div class="table-responsive">
            <table id="cryptoTable" class="table table-striped table-hover table-bordered" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Icône</th>
                        <th>Nom</th>
                        <th>Symbole</th>
                        <th>Prix (EUR)</th>
                        <th>Market Cap (EUR)</th>
                        <th>Variation 24h</th>
                        <th>Sparkline 7j</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coins as $coin): 
                        $sparklineData = json_decode($coin['sparkline'], true);
                        $sparklineJson = htmlspecialchars(json_encode($sparklineData), ENT_QUOTES);
                        $priceChange = (float)$coin['price_change_percentage_24h'];
                        $changeClass = $priceChange >= 0 ? 'positive' : 'negative';
                        $changeIcon = $priceChange >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
                        $marketCap = $coin['market_cap'] ?? 0;
                    ?>
                    <tr data-sparkline='<?= $sparklineJson ?>'>
                        <td><?= htmlspecialchars($coin['market_cap_rank']) ?></td>
                        <td><img src="<?= htmlspecialchars($coin['image']) ?>" class="coin-img rounded-circle" alt="<?= htmlspecialchars($coin['name']) ?>"></td>
                        <td><?= htmlspecialchars($coin['name']) ?></td>
                        <td class="text-uppercase"><?= htmlspecialchars($coin['symbol']) ?></td>
                        <td><?= number_format($coin['current_price'], 2, ',', ' ') ?> €</td>
                        <td><?= $marketCap >= 1e9 ? number_format($marketCap/1e9, 2).' Md €' : number_format($marketCap/1e6, 2).' M €' ?></td>
                        <td class="<?= $changeClass ?>">
                            <i class="bi <?= $changeIcon ?>"></i> <?= number_format($priceChange, 2) ?>%
                        </td>
                        <td>
                            <canvas class="sparkline-canvas" width="120" height="40" style="width:120px; height:40px;"></canvas>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <footer>
            <i class="bi bi-database"></i> Données stockées dans SQLite (crypto_cache.db) – API CoinGecko
        </footer>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Fonction pour dessiner un sparkline (ligne simple)
        function drawSparkline(canvas, prices) {
            if (!canvas || !prices || prices.length === 0) return;
            const ctx = canvas.getContext('2d');
            const w = canvas.width;
            const h = canvas.height;
            ctx.clearRect(0, 0, w, h);
            
            // Normaliser les prix
            const min = Math.min(...prices);
            const max = Math.max(...prices);
            const range = max - min;
            if (range === 0) return;
            
            const stepX = w / (prices.length - 1);
            
            ctx.beginPath();
            ctx.strokeStyle = '#3b82f6';
            ctx.lineWidth = 1.5;
            ctx.fillStyle = 'rgba(59,130,246,0.1)';
            
            let firstX = 0;
            let firstY = h - ((prices[0] - min) / range) * h;
            ctx.moveTo(firstX, firstY);
            
            // Tracer la ligne
            for (let i = 1; i < prices.length; i++) {
                const x = i * stepX;
                const y = h - ((prices[i] - min) / range) * h;
                ctx.lineTo(x, y);
            }
            ctx.stroke();
            
            // Remplissage sous la courbe
            ctx.lineTo(w, h);
            ctx.lineTo(0, h);
            ctx.closePath();
            ctx.fill();
        }
        
        $(document).ready(function() {
            // Initialisation DataTable (pagination, recherche, tri)
            const table = $('#cryptoTable').DataTable({
                pageLength: 50,
                lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Tous"]],
                order: [[0, 'asc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json"
                },
                drawCallback: function() {
                    // À chaque redessin (pagination/recherche), on redessine les sparklines des lignes visibles
                    $('tr:visible').each(function() {
                        const row = $(this);
                        const canvas = row.find('.sparkline-canvas')[0];
                        if (canvas && !canvas._drawn) {
                            const sparkJson = row.attr('data-sparkline');
                            if (sparkJson) {
                                try {
                                    const prices = JSON.parse(sparkJson);
                                    if (Array.isArray(prices) && prices.length) {
                                        drawSparkline(canvas, prices);
                                        canvas._drawn = true;
                                    }
                                } catch(e) { console.warn(e); }
                            }
                        }
                    });
                }
            });
            
            // Premier dessin
            table.draw();
        });
    </script>
</body>
</html>