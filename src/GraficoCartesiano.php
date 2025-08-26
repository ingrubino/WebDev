<?php
class GraficoCartesiano {
    private $matrix;
    private $titoloGrafico;
    private $titoloAsseX;
    private $titoloAsseY;
    private $larghezza;
    private $altezza;
    private $margine;
    private $colori;

    public function __construct($matrix = []) {
        $this->matrix = $matrix;
        $this->titoloGrafico = "Grafico Cartesiano";
        $this->titoloAsseX = "Asse X";
        $this->titoloAsseY = "Asse Y";
        $this->larghezza = 800;
        $this->altezza = 600;
        $this->margine = 60;
        
        $this->colori = [
            'griglia' => '#dcdcdc',
            'assi' => '#b4b4b4',
            'punti' => '#0064ff',
            'linea' => '#ff0000',
            'testo' => '#000000',
            'sfondo' => '#ffffff'
        ];
    }

    // Setter methods
    public function setMatrix($matrix) {
        $this->matrix = $matrix;
        return $this;
    }

    public function setTitoloGrafico($titolo) {
        $this->titoloGrafico = $titolo;
        return $this;
    }

    public function setTitoloAsseX($titolo) {
        $this->titoloAsseX = $titolo;
        return $this;
    }

    public function setTitoloAsseY($titolo) {
        $this->titoloAsseY = $titolo;
        return $this;
    }

    public function setDimensioni($larghezza, $altezza) {
        $this->larghezza = $larghezza;
        $this->altezza = $altezza;
        return $this;
    }

    public function setMargine($margine) {
        $this->margine = $margine;
        return $this;
    }

    public function setColori($colori) {
        $this->colori = array_merge($this->colori, $colori);
        return $this;
    }

    // Metodo principale per generare il grafico
    public function generaGrafico() {
        if (empty($this->matrix)) {
            return "<p>Nessun dato disponibile per generare il grafico</p>";
        }

        // Estrai e processa i dati
        $datiProcessati = $this->processaDati();
        $rangeX = $this->calcolaRange($datiProcessati['x']);
        $rangeY = $this->calcolaRange($datiProcessati['y']);

        // Genera SVG
        return $this->generaSVG($datiProcessati, $rangeX, $rangeY);
    }

    private function processaDati() {
        $datiX = [];
        $datiY = [];
        foreach ($this->matrix as $riga) {
            $datiX[] = (float)$riga[0];
            $datiY[] = (float)$riga[1];
        }
        return ['x' => $datiX, 'y' => $datiY];
    }

    private function calcolaRange($dati) {
        $min = min($dati);
        $max = max($dati);
        $range = $max - $min;
        return [
            'min' => $min - $range * 0.1,
            'max' => $max + $range * 0.1,
            'range' => $range
        ];
    }

    private function convertiCoordinata($x, $y, $minX, $maxX, $minY, $maxY) {
        $graficoLarghezza = $this->larghezza - 2 * $this->margine;
        $graficoAltezza = $this->altezza - 2 * $this->margine;
        if (($maxX - $minX)>0){
        $xPixel = $this->margine + (($x - $minX) / ($maxX - $minX)) * $graficoLarghezza;
        $yPixel = $this->altezza - $this->margine - (($y - $minY) / ($maxY - $minY)) * $graficoAltezza;
        }
        else
        {
            $xPixel = 1;    
            $yPixel = 1;
        } 
        return [round($xPixel, 2), round($yPixel, 2)];
    }

    private function generaSVG($dati, $rangeX, $rangeY) {
        $svg = '<svg width="' . $this->larghezza . '" height="' . $this->altezza . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= $this->getStiliCSS();
        $svg .= '<rect width="100%" height="100%" fill="' . $this->colori['sfondo'] . '" />';
        
        // Griglia e assi
        $svg .= $this->generaGriglia($rangeX, $rangeY);
        $svg .= $this->generaAssi($rangeX, $rangeY);
        
        // Punti e linee
        $svg .= $this->generaPuntiELinee($dati, $rangeX, $rangeY);
        
        // Titoli
        $svg .= $this->generaTitoli();
        
        $svg .= '</svg>';
        
        return $svg;
    }

    private function getStiliCSS() {
        return '
        <style>
            .griglia { stroke: ' . $this->colori['griglia'] . '; stroke-width: 1; }
            .assi { stroke: ' . $this->colori['assi'] . '; stroke-width: 2; }
            .punto { fill: ' . $this->colori['punti'] . '; stroke: ' . $this->colori['punti'] . '; }
            .linea { stroke: ' . $this->colori['linea'] . '; stroke-width: 2; fill: none; }
            .etichetta { font-family: Arial, sans-serif; font-size: 12px; fill: ' . $this->colori['testo'] . '; }
            .titolo { font-family: Arial, sans-serif; font-size: 16px; font-weight: bold; fill: ' . $this->colori['testo'] . '; }
        </style>';
    }

    private function generaGriglia($rangeX, $rangeY) {
        $svg = '';
        $graficoLarghezza = $this->larghezza - 2 * $this->margine;
        $graficoAltezza = $this->altezza - 2 * $this->margine;

        // Griglia orizzontale
        $passoGrigliaY = max(0.1, ceil($rangeY['range'] / 10));
        for ($y = ceil($rangeY['min'] / $passoGrigliaY) * $passoGrigliaY; $y <= $rangeY['max']; $y += $passoGrigliaY) {
            if ($y < $rangeY['min']) continue;
            list($x1, $y1) = $this->convertiCoordinata($rangeX['min'], $y, $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
            list($x2, $y2) = $this->convertiCoordinata($rangeX['max'], $y, $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
            $svg .= "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='griglia' />";
            $svg .= "<text x='" . ($this->margine - 25) . "' y='" . ($y1 + 4) . "' class='etichetta'>" . number_format($y, 1) . "</text>";
        }

        // Griglia verticale
        $passoGrigliaX = max(0.1, ceil($rangeX['range'] / 10));
        for ($x = ceil($rangeX['min'] / $passoGrigliaX) * $passoGrigliaX; $x <= $rangeX['max']; $x += $passoGrigliaX) {
            if ($x < $rangeX['min']) continue;
            list($x1, $y1) = $this->convertiCoordinata($x, $rangeY['min'], $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
            list($x2, $y2) = $this->convertiCoordinata($x, $rangeY['max'], $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
            $svg .= "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='griglia' />";
            $svg .= "<text x='" . ($x1 - 15) . "' y='" . ($this->altezza - $this->margine + 20) . "' class='etichetta'>" . number_format($x, 1) . "</text>";
        }

        return $svg;
    }

    private function generaAssi($rangeX, $rangeY) {
        $svg = '';
        
        // Asse X
        list($x1, $y1) = $this->convertiCoordinata($rangeX['min'], 0, $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
        list($x2, $y2) = $this->convertiCoordinata($rangeX['max'], 0, $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
        $svg .= "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='assi' />";

        // Asse Y
        list($x1, $y1) = $this->convertiCoordinata(0, $rangeY['min'], $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
        list($x2, $y2) = $this->convertiCoordinata(0, $rangeY['max'], $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
        $svg .= "<line x1='$x1' y1='$y1' x2='$x2' y2='$y2' class='assi' />";

        return $svg;
    }

    private function generaPuntiELinee($dati, $rangeX, $rangeY) {
        $svg = '';
        $pathData = '';

        foreach ($dati['x'] as $index => $x) {
            $y = $dati['y'][$index];
            list($xPixel, $yPixel) = $this->convertiCoordinata($x, $y, $rangeX['min'], $rangeX['max'], $rangeY['min'], $rangeY['max']);
            
            $svg .= "<circle cx='$xPixel' cy='$yPixel' r='4' class='punto' />";
            
            if ($index === 0) {
                $pathData .= "M $xPixel $yPixel ";
            } else {
                $pathData .= "L $xPixel $yPixel ";
            }
        }

        $svg .= "<path d='$pathData' class='linea' />";
        return $svg;
    }

    private function generaTitoli() {
        $svg = '';
        $svg .= "<text x='" . ($this->larghezza / 2) . "' y='30' class='titolo' text-anchor='middle'>" . htmlspecialchars($this->titoloGrafico) . "</text>";
        $svg .= "<text x='" . ($this->larghezza / 2) . "' y='" . ($this->altezza - 15) . "' class='titolo' text-anchor='middle'>" . htmlspecialchars($this->titoloAsseX) . "</text>";
        $svg .= "<text x='30' y='" . ($this->altezza / 2) . "' class='titolo' text-anchor='middle' transform='rotate(-90, 30, " . ($this->altezza / 2) . ")'>" . htmlspecialchars($this->titoloAsseY) . "</text>";
        
        return $svg;
    }
}
?>
