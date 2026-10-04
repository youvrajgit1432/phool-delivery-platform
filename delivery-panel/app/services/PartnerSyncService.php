<?php
namespace Phool\DeliveryPanel\Services;

use GuzzleHttp\Client as GuzzleClient;

class PartnerSyncService {
    protected $partnerApiKey;
    protected $partnerBaseUrl;
    protected $client;

    /**
     * Supported partners:
     * - bhatbhateni: Bhat Bhateni Supermarket
     * - pathao: Pathao Logistics
     * - daraz: Daraz Logistics
     * - sundhar: Sundhar Courier
     */

    public function __construct() {
        $this->partnerApiKey = getenv('PARTNER_API_KEY');
        $this->partnerBaseUrl = getenv('PARTNER_API_URL');
        $this->client = new GuzzleClient();
    }

    /**
     * Sync rider status with partner API
     * 
     * @param int $riderId
     * @param string $status online/offline
     * @param array|null $location Current location if online
     * @return bool
     */
    public function syncRiderStatus($riderId, $status, $location = null) {
        try {
            $payload = [
                'rider_id' => $riderId,
                'status' => $status,
                'timestamp' => time()
            ];

            if ($location) {
                $payload['location'] = [
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng']
                ];
            }

            $response = $this->client->post(
                $this->partnerBaseUrl . '/riders/status',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->partnerApiKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $payload
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log('Partner sync failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch orders from partner
     * 
     * @param string $partnerType bhatbhateni|pathao|daraz|sundhar
     * @param array $filters Zone, order status, etc
     * @return array Orders from partner
     */
    public function fetchPartnerOrders($partnerType, $filters = []) {
        try {
            $url = $this->partnerBaseUrl . "/orders/{$partnerType}";
            
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->partnerApiKey
                ],
                'query' => $filters
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['orders'] ?? [];
        } catch (\Exception $e) {
            error_log('Fetch partner orders failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Push delivery status to partner
     * 
     * @param int $orderId
     * @param string $partnerOrderId Partner's order ID
     * @param string $status Delivery status
     * @param array $metadata Proof, location, notes
     * @return bool
     */
    public function pushDeliveryStatus($orderId, $partnerOrderId, $status, $metadata = []) {
        try {
            $payload = [
                'order_id' => $partnerOrderId,
                'status' => $status,
                'timestamp' => time(),
                'metadata' => $metadata
            ];

            $response = $this->client->post(
                $this->partnerBaseUrl . '/deliveries/status',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->partnerApiKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $payload
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log('Push delivery status failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirm receipt and sync invoice
     * 
     * @param string $invoiceId Partner invoice ID
     * @param float $amount Amount received
     * @return bool
     */
    public function confirmPaymentReceipt($invoiceId, $amount) {
        try {
            $response = $this->client->post(
                $this->partnerBaseUrl . '/invoices/confirm',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->partnerApiKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'invoice_id' => $invoiceId,
                        'amount' => $amount,
                        'received_at' => date('Y-m-d H:i:s')
                    ]
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log('Confirm payment receipt failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch partner invoice/settlement
     * 
     * @param string $partnerId
     * @param string|null $month Month for invoice (Y-m format)
     * @return array Invoice details
     */
    public function getPartnerInvoice($partnerId, $month = null) {
        try {
            if (!$month) {
                $month = date('Y-m');
            }

            $response = $this->client->get(
                $this->partnerBaseUrl . "/invoices/{$partnerId}",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->partnerApiKey
                    ],
                    'query' => ['month' => $month]
                ]
            );

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            error_log('Fetch partner invoice failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Log sync event to activity log
     * 
     * @param string $action
     * @param array $details
     */
    protected function logSyncEvent($action, $details) {
        // Insert into activity_log table
    }

    /**
     * Retry failed sync operations
     * 
     * Useful for handling network issues
     */
    public function retryFailedSyncs() {
        // Query activity_log for failed syncs
        // Retry with exponential backoff
    }
}
