<?php
require('fpdf.php');

function erzeugeUrkunde($tnid, $name, $zeit, $platz, $ak, $akplatz)
{
  try { # SQL fehler fangen

        $linksrand=95;
        $oben=120;
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->Image('UrkundeERSETZEJAHR.jpg',5,5,200);
        $pdf->SetFont('Arial','B',32);
        $pdf->SetXY($linksrand,$oben);
        $pdf->Cell(80,40,utf8_decode($name));
        $pdf->SetFont('Arial','B',16);
        $pdf->SetXY($linksrand,$oben+50);
        $pdf->Cell(80,10,utf8_decode(""));
        $pdf->SetXY($linksrand,$oben+70);
        $pdf->Cell(80,10,"Serienwertung: ".utf8_decode($zeit));
        $pdf->SetXY($linksrand,$oben+80);
        $pdf->Cell(80,10,"Gesamtwertung: Platz ".utf8_decode($platz));
        $pdf->SetXY($linksrand,$oben+90);
        $pdf->Cell(80,10,"Altersklassenplatz ".utf8_decode($akplatz)." (".utf8_decode($ak).")");
        $pdf->Output("F","ERSETZEPDFFOLDER/nml-urkunde-ERSETZEJAHR-".$tnid.".pdf");
    } catch (Exception $e) {
      echo "Failed: " . $e->getMessage();
      die();
    }
}
?>
