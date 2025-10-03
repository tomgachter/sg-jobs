<?php

declare(strict_types=1);

namespace SGJobs\Infra\Bexio;

use GuzzleHttp\Exception\GuzzleException;
use SGJobs\Domain\Entity\Job;
use WP_Error;

class BexioService
{
    public function __construct(private BexioClient $client)
    {
    }

    /**
     * @return array{
     *     id:int,
     *     document_nr:string,
     *     customer_name:string,
     *     delivery_address:array<string, mixed>,
     *     phones:list<string>,
     *     sales_order_nr:string|null
     * }|WP_Error
     */
    public function getDeliveryNoteByNumber(string $number): array|WP_Error
    {
        try {
            $notes = $this->client->get('/kb_delivery_notes', ['document_nr' => $number]);
            if (! is_array($notes) || $notes === []) {
                return new WP_Error('sg_jobs_delivery_note_missing', __('Delivery note not found in bexio.', 'sg-jobs'));
            }
            $note = $notes[array_key_first($notes)];
            if (! is_array($note)) {
                return new WP_Error('sg_jobs_delivery_note_missing', __('Delivery note not found in bexio.', 'sg-jobs'));
            }
            /** @var array<string, mixed> $note */
            return [
                'id' => (int) $note['id'],
                'document_nr' => $note['document_nr'],
                'customer_name' => $note['contact_name'] ?? '',
                'delivery_address' => $note['delivery_address'],
                'phones' => $this->extractPhones($note),
                'sales_order_nr' => $note['reference'] ?? null,
            ];
        } catch (GuzzleException $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    /**
     * @return list<array{
     *     bexio_position_id:int,
     *     article_no:string,
     *     title:string,
     *     description:string,
     *     qty:float,
     *     unit:string
     * }>|WP_Error
     */
    public function getDeliveryNotePositions(int $deliveryNoteId): array|WP_Error
    {
        try {
            $positions = $this->client->get(sprintf('/kb_delivery_notes/%d/positions', $deliveryNoteId));
            if (! is_array($positions)) {
                return [];
            }

            $cleanPositions = [];
            foreach ($positions as $position) {
                if (is_array($position)) {
                    $cleanPositions[] = $position;
                }
            }

            return array_map(static function (array $position): array {
                return [
                    'bexio_position_id' => (int) $position['id'],
                    'article_no' => (string) ($position['article_nr'] ?? ''),
                    'title' => (string) $position['text'],
                    'description' => (string) ($position['description'] ?? ''),
                    'qty' => (float) $position['amount'],
                    'unit' => (string) $position['unit_name'],
                ];
            }, $cleanPositions);
        } catch (GuzzleException $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    public function appendNoteToDeliveryNote(int $deliveryNoteId, string $text): bool|WP_Error
    {
        try {
            $this->client->post(sprintf('/kb_delivery_notes/%d/comments', $deliveryNoteId), ['content' => $text]);
            return true;
        } catch (GuzzleException $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    public function getInvoicePaymentStatusForJob(Job $job): bool|WP_Error
    {
        $salesOrder = $job->salesOrderNumber();
        if (! $salesOrder) {
            return false;
        }

        try {
            $invoices = $this->client->get('/kb_invoices', ['document_nr' => $salesOrder]);
            if (! is_array($invoices) || $invoices === []) {
                return false;
            }
            $invoice = $invoices[array_key_first($invoices)];
            if (! is_array($invoice)) {
                return false;
            }
            /** @var array<string, mixed> $invoice */
            return isset($invoice['is_paid']) && (bool) $invoice['is_paid'];
        } catch (GuzzleException $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $note
     * @return list<string>
     */
    private function extractPhones(array $note): array
    {
        $phones = [];
        if (! empty($note['delivery_address']['phone'])) {
            $phones[] = $note['delivery_address']['phone'];
        }
        if (! empty($note['delivery_address']['mobile'])) {
            $phones[] = $note['delivery_address']['mobile'];
        }
        return $phones;
    }
}
