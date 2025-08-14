<?php

define('ROOT', getcwd() . '/');

spl_autoload_register(function($sClassName) {
    $sClassName = str_replace('\\', '/', $sClassName);
    if(file_exists(ROOT . $sClassName . '.php')) {
        require_once(ROOT . $sClassName . '.php');
    } else {
        return false;
    }
});

$oCache = json_decode(file_get_contents(ROOT . 'cache.json'));

$sPath = getenv('OneDrive') . '\BeterWerkt\Klanten\CSU\Betaalspecificaties\\';

$aFiles = scandir($sPath);

foreach($aFiles as $sFileName) {

    if(str_ends_with($sFileName, '.pdf')) {

        if(!isset($oCache->$sFileName)) {

            $oParser = new Smalot\PdfParser\Parser;

            $aInvoices = [];

            $oDocument = $oParser->parseFile($sPath . $sFileName);

            foreach($oDocument->getPages() as $oPage) {

                $aTextArray = $oPage->getTextArray();

                foreach($aTextArray as $sMaybeInvoiceNumber) {

                    if(strlen($sMaybeInvoiceNumber) == 8 && preg_match('/^2[5-9][0-9]{6}/', $sMaybeInvoiceNumber)) {

                        $aInvoices[] = $sMaybeInvoiceNumber;

                    }

                }

            }

            $oCache->$sFileName = array_unique($aInvoices);

        }

    }

}

file_put_contents(ROOT . 'cache.json', json_encode($oCache, JSON_PRETTY_PRINT));

$aPaidInvoices = [];

foreach($oCache as $key => $array) {
    $aPaidInvoices = array_merge($aPaidInvoices, (array)$array);
}

//var_dump(array_diff($aInvoicesList, $aPaidInvoices));

$rFile = fopen($sPath . 'Facturenlijst.csv', 'r');

$aHeader = fgetcsv($rFile, null, ';');

$oToday = new DateTime();

$sTotalsCSV = 'Factuurnummer;Datum;Bedrag;Hoe lang geleden;Betaald' . PHP_EOL;

$sUnpaidCSV = 'Factuurnummer;Datum;Bedrag;Hoe lang geleden' . PHP_EOL;

$sHTML = '<h3>Datum controle: ' . $oToday->format('Y-m-d H:i:s') . '</h3>';

$sHTML .= <<<STYLING

<style>
table {
  border-collapse: collapse;
  width: 400px;
}

th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #04AA6D;
  color: white;
}

tr:nth-child(even){background-color: #dadada;}

td {
    padding: 5px;
}
</style>
STYLING;

$sHTML .= '<table><tr><th>Factuur</th><th>Datum</th><th>Bedrag</th><th>Hoe lang geleden</th></tr>';

while(($aLine = fgetcsv($rFile, null, ';')) != false) {

    $sHTML .= '<tr>';

    $bPaid = in_array($aLine[0], $aPaidInvoices);

    $sHTML .= '<td style="color: ' . ($bPaid ? 'green' : 'red') . '">';
    $sHTML .= $aLine[0];
    $sHTML .= '</td>';
    $sHTML .= '<td>' . $aLine[1] . '</td><td>&euro; ' . $aLine[9] . '</td>';

    $oThisDate = date_create_from_format('d-m-Y', $aLine[1]);
    $iDayDifference = (date_diff($oToday, $oThisDate))->format('%a');

    $sHTML .= '<td style="color: ' . (($iDayDifference >= 30) ? 'red' : 'orange') . '">';

    $sHTML .= $iDayDifference . ' dagen geleden';

    $sHTML .= '</td>';
    $sHTML .= '</tr>';

    $sTotalsCSV .= $aLine[0] . ';' . $aLine[1] . ';' . $aLine[9] . ';' . $iDayDifference . ';' . ($bPaid ? 'Ja' : 'NEE') . PHP_EOL;
    if(!$bPaid) {

        $sUnpaidCSV .= $aLine[0] . ';' . $aLine[1] . ';' . $aLine[9] . ';' . $iDayDifference . PHP_EOL;

    }

}

$sHTML .= '</table>';

file_put_contents($sPath . 'tabel.htm', $sHTML);
file_put_contents($sPath . 'onbetaald.csv', $sUnpaidCSV);
file_put_contents($sPath . 'totaal.csv', $sTotalsCSV);

echo $sHTML;

?>