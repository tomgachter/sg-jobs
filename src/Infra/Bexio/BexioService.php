<?php

declare(strict_types=1);

namespace SGJobs\Infra\Bexio;

use GuzzleHttp\Exception\GuzzleException;
use SGJobs\Domain\Entity\Job;
use Throwable;
use WP_Error;

class BexioService
{
    public function __construct(private BexioClient $client)
    {
    }

    public function getDeliveryNoteByNumber(string $number): array|WP_Error
    {
        try {
            $notes = $this->client->get('/kb_delivery_notes', ['document_nr' => $number]);
            if (! $notes) {
                return new WP_Error('sg_jobs_delivery_note_missing', __('Delivery note not found in bexio.', 'sg-jobs'));
            }
            $note = $notes[0];
            return [
                'id' => (int) $note['id'],
                'document_nr' => $note['document_nr'],
                'customer_name' => $note['contact_name'] ?? '',
                'delivery_address' => $note['delivery_address'],
                'phones' => $this->extractPhones($note),
                'sales_order_nr' => $note['reference'] ?? null,
            ];
        } catch (GuzzleException|Throwable $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    public function getDeliveryNotePositions(int $deliveryNoteId): array|WP_Error
    {
        try {
            $positions = $this->client->get(sprintf('/kb_delivery_notes/%d/positions', $deliveryNoteId));
            return array_map(static function (array $position): array {
                return [
                    'bexio_position_id' => (int) $position['id'],
                    'article_no' => (string) ($position['article_nr'] ?? ''),
                    'title' => (string) $position['text'],
                    'description' => (string) ($position['description'] ?? ''),
                    'qty' => (float) $position['amount'],
                    'unit' => (string) $position['unit_name'],
                ];
            }, $positions);
        } catch (GuzzleException|Throwable $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    public function appendNoteToDeliveryNote(int $deliveryNoteId, string $text): bool|WP_Error
    {
        try {
            $this->client->post(sprintf('/kb_delivery_notes/%d/comments', $deliveryNoteId), ['content' => $text]);
            return true;
        } catch (GuzzleException|Throwable $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    public function ping(): bool|WP_Error
    {
        try {
            $this->client->get('/kb_delivery_notes', ['limit' => 1]);

            return true;
        } catch (GuzzleException|Throwable $exception) {
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
            if (! $invoices) {
                return false;
            }
            $invoice = $invoices[0];
            return isset($invoice['is_paid']) && (bool) $invoice['is_paid'];
        } catch (GuzzleException|Throwable $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

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
