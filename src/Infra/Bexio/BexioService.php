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

    /**
     * @return array{
     *     id: int,
     *     document_nr: string,
     *     customer_name: string,
     *     delivery_address: array,
     *     phones: array<int, string>,
     *     sales_order_nr: ?string
     * }|WP_Error
     */
    public function getDeliveryNoteByNumber(string $number): array|WP_Error
    {
        try {
            $notes = $this->client->get('/kb_delivery_notes', ['document_nr' => $number]);
            if (! $notes) {
                return new WP_Error('sg_jobs_delivery_note_missing', __('Delivery note not found in bexio.', 'sg-jobs'));
            }
            $note = $notes[0];

            $deliveryAddress = is_array($note['delivery_address'] ?? null) ? $note['delivery_address'] : [];

            return [
                'id' => (int) $note['id'],
                'document_nr' => (string) $note['document_nr'],
                'customer_name' => (string) ($note['contact_name'] ?? ''),
                'delivery_address' => $deliveryAddress,
                'phones' => $this->extractPhones($note),
                'sales_order_nr' => isset($note['reference']) && $note['reference'] !== ''
                    ? (string) $note['reference']
                    : null,
            ];
        } catch (GuzzleException|Throwable $exception) {
            return new WP_Error('sg_jobs_bexio_error', $exception->getMessage());
        }
    }

    /**
     * @return array<int, array{
     *     bexio_position_id: int,
     *     article_no: string,
     *     title: string,
     *     description: string,
     *     qty: float,
     *     unit: string
     * }>|WP_Error
     */
    public function getDeliveryNotePositions(int $deliveryNoteId): array|WP_Error
    {
        try {
            $positions = $this->client->get(sprintf('/kb_delivery_notes/%d/positions', $deliveryNoteId));

            if (! is_array($positions)) {
                return [];
            }

            usort($positions, static function (array $left, array $right): int {
                $leftSort = $left['position'] ?? $left['id'] ?? 0;
                $rightSort = $right['position'] ?? $right['id'] ?? 0;

                return (int) $leftSort <=> (int) $rightSort;
            });

            return array_map(static function (array $position): array {
                return [
                    'bexio_position_id' => (int) $position['id'],
                    'article_no' => (string) ($position['article_nr'] ?? ''),
                    'title' => (string) ($position['text'] ?? ''),
                    'description' => (string) ($position['description'] ?? ''),
                    'qty' => (float) ($position['amount'] ?? 0),
                    'unit' => (string) ($position['unit_name'] ?? ''),
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

    /**
     * @param array<string, mixed> $note
     *
     * @return array<int, string>
     */
    private function extractPhones(array $note): array
    {
        $candidates = [];
        $delivery = is_array($note['delivery_address'] ?? null) ? $note['delivery_address'] : [];
        $contact = is_array($note['contact'] ?? null) ? $note['contact'] : [];

        foreach (['phone', 'mobile', 'phone_fixed', 'phone_mobile'] as $field) {
            if (! empty($delivery[$field])) {
                $candidates[] = (string) $delivery[$field];
            }
        }

        foreach (['phone_fixed', 'phone_mobile', 'phone_direct', 'mobile'] as $field) {
            if (! empty($contact[$field])) {
                $candidates[] = (string) $contact[$field];
            }
        }

        $phones = array_values(array_unique(array_map(static function (string $phone): string {
            return trim($phone);
        }, array_filter($candidates, static fn ($phone): bool => is_string($phone) && $phone !== ''))));

        return array_values(array_filter($phones, static fn (string $phone): bool => $phone !== ''));
    }
}
