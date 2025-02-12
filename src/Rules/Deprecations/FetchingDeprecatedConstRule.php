<?php declare(strict_types = 1);

namespace PHPStan\Rules\Deprecations;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function sprintf;
use const PHP_VERSION_ID;

/**
 * @implements Rule<ConstFetch>
 */
class FetchingDeprecatedConstRule implements Rule
{

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var DeprecatedScopeHelper */
	private $deprecatedScopeHelper;

	/** @var array<string,string> */
	private $deprecatedConstants = [];

	public function __construct(ReflectionProvider $reflectionProvider, DeprecatedScopeHelper $deprecatedScopeHelper)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->deprecatedScopeHelper = $deprecatedScopeHelper;

		// phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
		if (PHP_VERSION_ID >= 70300) {
			$this->deprecatedConstants['FILTER_FLAG_SCHEME_REQUIRED'] = 'Use of constant %s is deprecated since PHP 7.3.';
			$this->deprecatedConstants['FILTER_FLAG_HOST_REQUIRED'] = 'Use of constant %s is deprecated since PHP 7.3.';
		}
	}

	public function getNodeType(): string
	{
		return ConstFetch::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($this->deprecatedScopeHelper->isScopeDeprecated($scope)) {
			return [];
		}

		if (!$this->reflectionProvider->hasConstant($node->name, $scope)) {
			return [];
		}

		$constantReflection = $this->reflectionProvider->getConstant($node->name, $scope);

		if ($constantReflection->isDeprecated()->yes()) {
			return [
				RuleErrorBuilder::message(sprintf(
					$constantReflection->getDeprecatedDescription() ?? 'Use of constant %s is deprecated.',
					$constantReflection->getName()
				))->identifier('constant.deprecated')->build(),
			];
		}

		if (isset($this->deprecatedConstants[$constantReflection->getName()])) {
			return [
				RuleErrorBuilder::message(sprintf(
					$this->deprecatedConstants[$constantReflection->getName()],
					$constantReflection->getName()
				))->identifier('constant.deprecated')->build(),
			];
		}

		return [];
	}

}
