<?php

namespace DigitalElvis\NeuronAIStudio\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Demo refund tool for the parallel-refund-approval template.
 * Class-based (no Closure) so WorkflowInterrupt serialization works (AD-005).
 */
class IssueRefundTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'issue_refund',
            'Issue a refund for an order. Returns a simulated refund confirmation.',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'order_id',
                type: PropertyType::STRING,
                description: 'Order identifier to refund (e.g. 4821)',
                required: true,
            ),
            new ToolProperty(
                name: 'amount',
                type: PropertyType::STRING,
                description: 'Refund amount as a decimal string (e.g. 189.90)',
                required: true,
            ),
            new ToolProperty(
                name: 'reason',
                type: PropertyType::STRING,
                description: 'Short reason for the refund',
                required: true,
            ),
        ];
    }

    public function __invoke(string $order_id, string $amount, string $reason): string
    {
        $orderId = trim($order_id);
        $amountValue = trim($amount);
        $reasonValue = trim($reason);

        if ($orderId === '' || $amountValue === '' || $reasonValue === '') {
            return json_encode([
                'status' => 'error',
                'message' => 'order_id, amount, and reason are required.',
            ], JSON_THROW_ON_ERROR);
        }

        $refundId = 'rfnd_'.substr(sha1($orderId.'|'.$amountValue.'|'.$reasonValue), 0, 12);

        return json_encode([
            'status' => 'refunded',
            'refund_id' => $refundId,
            'order_id' => $orderId,
            'amount' => $amountValue,
            'reason' => $reasonValue,
            'simulated' => true,
            'message' => "Simulated refund {$refundId} issued for order {$orderId} ({$amountValue}).",
        ], JSON_THROW_ON_ERROR);
    }
}
