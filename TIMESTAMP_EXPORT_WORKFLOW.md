# Timestamp Export Workflow - JSON and ASN1 File Assembly

**Antwort auf: "wo werden die json asn1 files für timestamp export zusammen gesetzt"**

Dieses Dokument erklärt, wo und wie die JSON- und ASN1-Dateien für den Timestamp-Export in eLabFTW zusammengesetzt werden.

## Überblick

Der Timestamp-Export in eLabFTW erstellt ein ZIP-Archiv, das zwei Hauptkomponenten enthält:
1. **Datendatei** (JSON oder PDF) - der Inhalt, der mit einem Zeitstempel versehen wird
2. **ASN1-Token** - der kryptographische Zeitstempel nach RFC 3161

## Workflow und Dateizusammensetzung

### 1. Datengeneration (JSON-Erstellung)

**Ort:** `src/Make/AbstractMakeTimestamp.php` → `generateData()` → `generateJson()`

```php
private function generateJson(): string
{
    // Vollständiger JSON-Export der Entity-Daten
    $MakeJson = new MakeFullJson(array($this->entity));
    return $MakeJson->getFileContent();
}
```

**Was passiert:**
- `MakeFullJson` erstellt einen vollständigen Export aller Entity-Daten
- Verwendet `entity->readOneFull()` für komplette Datenextraktion
- JSON wird als String zurückgegeben und in temporärer Datei gespeichert

### 2. ASN1-Token-Erstellung

**Ort:** `src/Services/TimestampUtils.php` → `timestamp()`

```php
public function timestamp(): TimestampResponse
{
    $requestFilePath = $this->createRequestfile();      // OpenSSL Anfrage erstellen
    $response = $this->postData($requestFilePath);      // An TSA senden
    // ASN.1 Token von TSA-Antwort speichern
    $this->cacheFs->write(basename($this->tsResponse->tokenPath), $response->getBody()->getContents());
    $this->verify();                                    // Token verifizieren
    return $this->tsResponse;
}
```

**Was passiert:**
- OpenSSL erstellt Timestamp-Anfrage aus Datendatei
- Anfrage wird an Timestamp Authority (TSA) gesendet
- TSA-Antwort enthält ASN.1 DER-kodierten Zeitstempel-Token
- Token wird in temporärer Datei gespeichert (`$tsResponse->tokenPath`)

### 3. Finale Zusammensetzung (Assembly)

**Ort:** `src/Make/AbstractMakeTrustedTimestamp.php` → `saveTimestamp()`

```php
public function saveTimestamp(TimestampResponse $tsResponse, CreateUploadParamsInterface $create): int
{
    $zipName = $create->getFileName();                   // z.B. 20220210171842-timestamp.zip
    $dataName = str_replace('zip', $this->dataFormat->value, $zipName);  // 20220210171842-timestamp.json
    $tokenName = str_replace('zip', 'asn1', $zipName);   // 20220210171842-timestamp.asn1

    // ZIP-Archiv erstellen und beide Dateien hinzufügen
    $ZipArchive = new ZipArchive();
    $ZipArchive->open($create->getFilePath(), ZipArchive::CREATE);
    $ZipArchive->addFile($tsResponse->dataPath, $dataName);    // JSON/PDF-Datei
    $ZipArchive->addFile($tsResponse->tokenPath, $tokenName);  // ASN1-Token
    $ZipArchive->close();
    
    return $this->entity->Uploads->create($create, isTimestamp: true);
}
```

**Was passiert:**
- Beide temporären Dateien werden in ein ZIP-Archiv gepackt
- Datendatei wird zu `timestamp.json` oder `timestamp.pdf` umbenannt
- Token-Datei wird zu `timestamp.asn1` umbenannt
- Finales ZIP-Archiv wird als Upload gespeichert

## Dateipfad-Verwaltung

**Ort:** `src/Elabftw/TimestampResponse.php`

```php
class TimestampResponse
{
    public readonly string $dataPath;    // Pfad zur JSON/PDF-Datei
    public readonly string $tokenPath;   // Pfad zur ASN1-Token-Datei

    public function __construct()
    {
        $this->dataPath = FsTools::getCacheFile();   // Temporäre Datei für Daten
        $this->tokenPath = FsTools::getCacheFile();  // Temporäre Datei für Token
    }
}
```

## Architektur-Übersicht

```
AbstractMakeTimestamp::generateData()
├── generateJson() → MakeFullJson → JSON-String
└── generatePdf() → MakeTimestampPdf → PDF-Bytes
                    ↓
            TimestampUtils::timestamp()
            ├── createRequestfile() → OpenSSL Anfrage
            ├── postData() → TSA-Kommunikation
            └── ASN.1-Token speichern
                    ↓
        AbstractMakeTrustedTimestamp::saveTimestamp()
        └── ZIP-Archiv erstellen
            ├── Datendatei hinzufügen (.json/.pdf)
            └── Token-Datei hinzufügen (.asn1)
```

## Implementierungsbeispiele

### DFN Timestamp Service
**Datei:** `src/Make/MakeDfnTimestamp.php`
- Verwendet kostenlose DFN TSA (https://zeitstempel.dfn.de)
- SHA-256 Hash-Algorithmus
- Benötigt DFN-Zertifikate für Verifikation

### Andere TSA-Implementierungen
- `MakeDigicertTimestamp.php` - DigiCert TSA
- `MakeGlobalSignTimestamp.php` - GlobalSign TSA
- `MakeUniversignTimestamp.php` - Universign TSA

## Temporäre Dateien

Alle temporären Dateien werden automatisch durch `TimestampUtils::__destruct()` gelöscht:

```php
public function __destruct()
{
    foreach ($this->trash as $file) {
        $this->cacheFs->delete($file);
    }
}
```

## Fazit

Die JSON- und ASN1-Dateien für den Timestamp-Export werden an drei Hauptstellen zusammengesetzt:

1. **JSON-Erstellung**: `AbstractMakeTimestamp::generateJson()`
2. **ASN1-Token-Erstellung**: `TimestampUtils::timestamp()`
3. **Finale Zusammensetzung**: `AbstractMakeTrustedTimestamp::saveTimestamp()`

Das Endresultat ist ein ZIP-Archiv mit beiden Dateien, das als sicherer Nachweis für den Zeitpunkt der Daten dient.