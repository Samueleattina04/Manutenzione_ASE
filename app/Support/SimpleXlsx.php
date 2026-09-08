<?php

namespace App\Support;

use ZipArchive;

/**
 * Generatore minimale di file .xlsx (Excel) senza dipendenze esterne.
 * Un file .xlsx è uno ZIP di documenti XML: qui produciamo un singolo
 * foglio con intestazione in grassetto e testo a capo automatico.
 *
 * Tutti i valori sono scritti come stringhe "inline" per evitare
 * interpretazioni errate (numeri, date, zeri iniziali).
 */
class SimpleXlsx
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  array<int,string>       $headers  intestazioni di colonna
     * @param  array<int,array<int,?string>> $rows  righe di dati
     * @param  array<int,int|float>    $widths   larghezze colonne (opzionali)
     * @return string  contenuto binario del file .xlsx
     */
    public static function build(array $headers, array $rows, array $widths = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::RELS);
        $zip->addFromString('xl/workbook.xml', self::WORKBOOK);
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::WORKBOOK_RELS);
        $zip->addFromString('xl/styles.xml', self::STYLES);
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheetXml($headers, $rows, $widths));

        $zip->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    private static function sheetXml(array $headers, array $rows, array $widths): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($widths) {
            $xml .= '<cols>';
            foreach (array_values($widths) as $i => $w) {
                $c = $i + 1;
                $xml .= '<col min="'.$c.'" max="'.$c.'" width="'.(float) $w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        $xml .= self::rowXml(1, array_values($headers), 1);       // intestazione (grassetto)
        $n = 2;
        foreach ($rows as $row) {
            $xml .= self::rowXml($n++, array_values($row), 2);     // dati (a capo automatico)
        }
        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    private static function rowXml(int $rownum, array $cells, int $style): string
    {
        $x = '<row r="'.$rownum.'">';
        foreach (array_values($cells) as $i => $val) {
            $ref = self::colLetter($i + 1).$rownum;
            $x .= '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'
                .self::esc($val).'</t></is></c>';
        }

        return $x.'</row>';
    }

    /** Indice colonna (1-based) -> lettere (A, B, …, Z, AA, …). */
    private static function colLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    private static function esc(?string $v): string
    {
        $v = (string) $v;
        // Rimuove i caratteri di controllo non ammessi in XML (tiene \t \n \r).
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? $v;

        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private const CONTENT_TYPES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        .'</Types>';

    private const RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>';

    private const WORKBOOK = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Richieste" sheetId="1" r:id="rId1"/></sheets>'
        .'</workbook>';

    private const WORKBOOK_RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        .'</Relationships>';

    private const STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="2">'
        .'<font><sz val="11"/><name val="Calibri"/></font>'
        .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
        .'</fonts>'
        .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        .'<borders count="1"><border/></borders>'
        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        .'<cellXfs count="3">'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
        .'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>';
}
