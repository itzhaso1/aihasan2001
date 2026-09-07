<?php

namespace App\Services\Domain;

use SimpleXMLElement;

class NamecheapXmlParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml);
        libxml_clear_errors();

        if (! $element instanceof SimpleXMLElement) {
            throw new DomainProviderException('Invalid XML response from Namecheap.');
        }

        $status = strtoupper((string) ($element['Status'] ?? 'ERROR'));
        $errors = [];
        if (isset($element->Errors->Error)) {
            foreach ($element->Errors->Error as $error) {
                $errors[] = [
                    'number' => (string) ($error['Number'] ?? 'unknown'),
                    'message' => trim((string) $error),
                ];
            }
        }

        $commandResponse = isset($element->CommandResponse) ? $this->toArray($element->CommandResponse) : [];

        return [
            'status' => $status,
            'requested_command' => (string) ($element->RequestedCommand ?? ''),
            'errors' => $errors,
            'command_response' => $commandResponse,
            'raw' => $xml,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(SimpleXMLElement $element): array
    {
        $result = [];

        foreach ($element->attributes() as $name => $value) {
            $result[(string) $name] = (string) $value;
        }

        foreach ($element->children() as $name => $child) {
            $value = $this->toArray($child);
            if ($value === []) {
                $value = trim((string) $child);
            }

            if (array_key_exists($name, $result)) {
                if (! is_array($result[$name]) || ! array_is_list($result[$name])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $value;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
