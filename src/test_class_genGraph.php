<?php
require_once 'GraficoCartesiano.php';

// Dati del grafico
$matrix = [
    ['0.1', '2.3'],
    ['0.5', '4.1'],
    ['1.2', '3.8'],
    ['1.8', '5.2'],
    ['2.5', '6.1'],
    ['3.0', '4.9'],
    ['3.7', '7.3'],
    ['4.2', '8.1'],
    ['4.8', '6.7'],
    ['5.5', '9.2']
];

// Creazione dell'istanza
$grafico = new GraficoCartesiano($matrix);

// Personalizzazione (opzionale)
$grafico->setTitoloGrafico("Andamento Prezzi")
        ->setTitoloAsseX("Tempo (ore)")
        ->setTitoloAsseY("Prezzo ($)")
        ->setDimensioni(900, 500)
        ->setMargine(70)
        ->setColori([
            'punti' => '#ff6600',
            'linea' => '#009900',
            'griglia' => '#e0e0e0'
        ]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Grafico Cartesiano</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>Il mio Grafico Dinamico</h1>
    
    <div style="border: 1px solid #ccc; padding: 20px; margin: 20px;">
        <h2>Grafico generato con PHP Class</h2>
        <?php echo $grafico->generaGrafico(); ?>
    </div>

    <div style="border: 1px solid #ccc; padding: 20px; margin: 20px;">
        <h2>Altro grafico con impostazioni diverse</h2>
        <?php
        // Secondo grafico con impostazioni diverse
        $grafico2 = new GraficoCartesiano($matrix);
        $grafico2->setTitoloGrafico("Secondo Grafico")
                 ->setDimensioni(600, 400);
        echo $grafico2->generaGrafico();
        ?>
    </div>
</body>
</html>