<?php
require('fpdf.php');

function output_pdf($file)
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.basename($file).'"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
}

function erzeugeUrkunde($name, $verein, $zeit, $platz, $ak, $akplatz, $bonus, $laufergebnisse, $ausgabe)
{
  try
  {
      $ort = "ERSETZEDEPLOYFOLDER/nml-urkunden/Urkunde-Nord-Muensterland-ERSETZEJAHR-".$tnid.".pdf";
      if(!file_exists($ausgabe)) {
          $linksrand=25;
          $oben=95;
          $w=120;
          $y=$oben;
          $pdf = new FPDF('P','mm','A4');
          $pdf->AddPage();
          $pdf->Image('ERSETZEDEPLOYFOLDER/adm/Urkunde2019-Michaela.png',5,5,200);
          $pdf->SetFont('Arial','B',32);
          $pdf->SetXY($linksrand,$y);
          $pdf->MultiCell(160,16,utf8_decode($platz).". Platz",0,'C');
          $pdf->SetXY($linksrand,$y+16);
          $pdf->MultiCell(160,16,utf8_decode($name),0,'C');
          
          $pdf->SetFont('Arial','B',16);
          $h2=12;
          $y=$oben+32;
          $pdf->SetXY($linksrand,$y);
          $pdf->MultiCell(160,$h2,utf8_decode($verein),0,'C');
          $y=$y+$h2;
          $pdf->SetXY($linksrand,$y);
          $pdf->MultiCell(160,$h2,utf8_decode($akplatz).". Platz in der Altersklasse ".utf8_decode($ak),0,'C');
          $y=$y+$h2;
          $pdf->SetXY($linksrand,$y);
          $pdf->MultiCell(160,$h2,utf8_decode($zeit),0,'C');
          $y=$y+$h2;
          foreach($laufergebnisse as $lauf => $lzeit)
          {
              $pdf->SetXY($linksrand,$y);
              $pdf->MultiCell(160,$h2,mb_convert_encoding( $lauf, 'Windows-1252', 'UTF-8'),0,'L');
              $pdf->SetXY($linksrand,$y);
              $pdf->MultiCell(160,$h2,utf8_decode($lzeit),0,'R');
              $y=$y+$h2;
          }
          $pdf->SetXY($linksrand,$y);
          $pdf->MultiCell(160,$h2,"Bonus: ".utf8_decode($bonus),0,'C');
          $y=$y+$h2;
          
          $pdf->Output("F",$ausgabe);
      }
      output_pdf($ausgabe);
      
    } catch (Exception $e) {
      echo "Failed: " . $e->getMessage();
      die();
    }
}
?>
