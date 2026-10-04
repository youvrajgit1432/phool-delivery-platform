<?php
namespace Phool\DeliveryPanel\Middleware;

class RoleMiddleware {
    /**
     * Verify rider has required role/type
     * 
     * Rider types:
     * - in_house: Phool salaried riders
     * - gig: Per-order gig workers
     * - partner: External logistics partners
     * 
     * @param array $request
     * @param array|string $requiredRoles
     * @return bool
     */
    public function handle($request, $requiredRoles = []) {
        if (!isset($_SESSION['rider_id'])) {
            http_response_code(401);
            return false;
        }

        // Allow array or single string
        if (!is_array($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }

        // Get current rider's type from session
        $riderType = $_SESSION['rider_type'] ?? null;

        if (!in_array($riderType, $requiredRoles)) {
            http_response_code(403);
            return false;
        }

        return true;
    }

    /**
     * Restrict to in-house riders only
     */
    public function requireInHouse() {
        return $this->handle([], ['in_house']);
    }

    /**
     * Restrict to gig riders
     */
    public function requireGig() {
        return $this->handle([], ['gig']);
    }

    /**
     * Restrict to partners
     */
    public function requirePartner() {
        return $this->handle([], ['partner']);
    }
}
