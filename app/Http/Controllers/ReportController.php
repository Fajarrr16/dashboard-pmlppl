<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ReportController extends Controller
{
    public function perjadin(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'executor' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'string', 'max:50'],
            'letter' => ['nullable', 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:50'],
            'rw' => ['nullable', 'string', 'max:50'],
            'kelurahan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'family_count' => ['nullable', 'string', 'max:50'],
            'new_business' => ['nullable', 'string', 'max:50'],
            'new_family' => ['nullable', 'string', 'max:50'],
            'signatory' => ['nullable', 'string', 'max:255'],
        ]);

        $templatePath = base_path('Laporan Perjadin Revisit & Penyisiran SE2026.docx');

        abort_unless(is_file($templatePath), 404, 'Template laporan tidak ditemukan.');

        $temporaryDirectory = storage_path('app');
        $temporaryPath = tempnam($temporaryDirectory, 'perjadin_');
        copy($templatePath, $temporaryPath);

        $zip = new ZipArchive;
        abort_unless($zip->open($temporaryPath) === true, 500, 'Template laporan tidak dapat dibuka.');

        $documentXml = $zip->getFromName('word/document.xml');
        $document = new \DOMDocument;
        $document->preserveWhiteSpace = false;
        $document->loadXML($documentXml);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $rows = $xpath->query('//w:tbl[1]/w:tr');
        $this->setCellText($xpath, $rows->item(2)?->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tc')->item(0), $data['title'] ?? '');
        $this->setCellText($xpath, $rows->item(2)?->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tc')->item(3), $data['executor'] ?? '');
        $this->setCellText($xpath, $rows->item(3)?->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tc')->item(2), $data['date'] ?? '');
        $this->setCellText($xpath, $rows->item(4)?->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tc')->item(2), $data['letter'] ?? '');

        $this->replaceParagraphContaining($xpath, 'Pada hari ini', sprintf(
            'Pada hari ini, %s tanggal %s bulan September tahun 2026, saya melakukan perjalanan dalam rangka revisit dan penyisiran pendataan SE2026 ke RT %s RW %s Kelurahan %s Kecamatan %s.',
            $data['date'] ?? '',
            $data['date'] ?? '',
            $data['rt'] ?? '',
            $data['rw'] ?? '',
            $data['kelurahan'] ?? '',
            $data['kecamatan'] ?? '',
        ));
        $this->replaceParagraphContaining($xpath, 'Jumlah keluarga di SLS', 'Jumlah keluarga di SLS menurut pengurus SLS: '.$data['family_count'].' keluarga');
        $this->replaceParagraphContaining($xpath, 'Jumlah usaha baru yang ditemukan', 'Jumlah usaha baru yang ditemukan (new assignment): '.$data['new_business'].' usaha');
        $this->replaceParagraphContaining($xpath, 'Jumlah keluarga baru yang ditemukan', 'Jumlah keluarga baru yang ditemukan (new assignment): '.$data['new_family'].' keluarga');
        $this->replaceParagraphContaining($xpath, 'Kegiatan revisit dan penyisiran pendataan', sprintf(
            'Kegiatan revisit dan penyisiran pendataan SE2026 pada hari ini di wilayah RT %s RW %s telah dilaksanakan dan berjalan dengan lancar. Kegiatan revisit dan penyisiran hasil pendataan Sensus Ekonomi 2026 dilakukan untuk memverifikasi kebenaran data, mengonfirmasi keberadaan usaha/keluarga untuk memastikan semua pelaku usaha terjangkau dalam Sensus Ekonomi 2026.',
            $data['rt'] ?? '',
            $data['rw'] ?? '',
        ));
        $this->replaceParagraphContaining($xpath, 'Agung Erianto Juliandono', $data['signatory'] ?? '');

        $zip->addFromString('word/document.xml', $document->saveXML());
        $zip->close();

        return response()->download(
            $temporaryPath,
            'Laporan Perjadin Revisit dan Penyisiran SE2026.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }

    private function replaceParagraphContaining(\DOMXPath $xpath, string $needle, string $text): void
    {
        foreach ($xpath->query('//w:p') as $paragraph) {
            if (stripos($paragraph->textContent, $needle) !== false) {
                $this->setText($xpath, $paragraph, $text);

                return;
            }
        }
    }

    private function setCellText(\DOMXPath $xpath, ?\DOMElement $cell, string $text): void
    {
        if ($cell) {
            $this->setText($xpath, $cell, $text);
        }
    }

    private function setText(\DOMXPath $xpath, \DOMElement $container, string $text): void
    {
        $textNodes = iterator_to_array($xpath->query('.//w:t', $container));
        $firstText = $textNodes[0] ?? null;

        foreach (array_slice($textNodes, 1) as $textNode) {
            $textNode->parentNode?->removeChild($textNode);
        }

        if ($firstText) {
            $firstText->nodeValue = $text;

            return;
        }

        $paragraph = $container->localName === 'p'
            ? $container
            : $xpath->query('.//w:p[1]', $container)->item(0);

        if (! $paragraph) {
            return;
        }

        $run = $document = $paragraph->ownerDocument->createElementNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'w:r'
        );
        $textElement = $paragraph->ownerDocument->createElementNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'w:t'
        );
        $textElement->appendChild($paragraph->ownerDocument->createTextNode($text));
        $run->appendChild($textElement);
        $paragraph->appendChild($run);
    }
}
