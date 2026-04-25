<?php
/**
 * This file is part of the Orangecat PurchaseOrder package.
 *
 * (c) Oliverio Gombert <olivertar@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Orangecat\PurchaseOrder\Model;

use Magento\Quote\Api\Data\CartInterface;
use Orangecat\PurchaseOrder\Model\Rule\ApprovalRuleInterface;
use Psr\Log\LoggerInterface;

/**
 * Evaluates a set of ApprovalRules against a cart and customer.
 *
 * Returns true (approval required) as soon as any rule returns true
 * (short-circuit OR logic). Returns false only when every rule passes.
 *
 * This class also implements ApprovalRuleInterface following the
 * Composite pattern, which means a chain can itself be used as a
 * named rule inside another chain. This enables rule grouping in the
 * future (e.g. "buyer rules", "manager rules") purely through di.xml
 * without touching PHP.
 *
 * Adding a new rule:
 *   1. Create a class that implements ApprovalRuleInterface.
 *   2. Add it to the "rules" argument of this type in di.xml.
 *   3. Done — no other files need to change.
 */
class ApprovalRuleChain implements ApprovalRuleInterface
{

    /**
     * @param LoggerInterface $logger
     * @param ApprovalRuleInterface[] $rules Injected by di.xml
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private array $rules = []
    ) {
        foreach ($rules as $name => $rule) {
            if (!$rule instanceof ApprovalRuleInterface) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Approval rule "%s" must implement %s, got %s.',
                        $name,
                        ApprovalRuleInterface::class,
                        get_class($rule)
                    )
                );
            }
        }
    }

    /**
     * @inheritdoc
     *
     * Iterates rules in declaration order.  Stops and returns true on the
     * first rule that requires approval (short-circuit evaluation).
     * Returns false if the rule list is empty (safe default: allow checkout).
     */
    public function needsApproval(CartInterface $quote, int $customerId): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule->needsApproval($quote, $customerId)) {
                $this->logger->info(sprintf(
                    '[PurchaseOrder][ApprovalRuleChain] Rule "%s" requires approval '
                    . 'for customer %d on quote %d.',
                    $rule->getRuleName(),
                    $customerId,
                    (int) $quote->getId()
                ));

                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     *
     * Returns "approval_rule_chain" as the composite rule name.
     * Override this in di.xml via a virtual type if you want a named sub-chain.
     */
    public function getRuleName(): string
    {
        return 'approval_rule_chain';
    }

    /**
     * Return all registered rules (useful for diagnostics/testing).
     *
     * @return ApprovalRuleInterface[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
