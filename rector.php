<?php

/**
 * Adapted from bitrix24/b24phpsdk v3, commit 8ebd4c154d5557db949b0196825f347a3a6c5bf0.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/config', __DIR__ . '/tests/Offline', __DIR__ . '/tests/Integration', __DIR__ . '/tests/Support', __DIR__ . '/bin/console', __DIR__ . '/bin/b24cli'])
    ->withFileExtensions(['php', 'console', 'b24cli'])
    ->withCache(cacheDirectory: __DIR__ . '/var/cache/rector')
    ->withSets(
        [
            LevelSetList::UP_TO_PHP_84
        ]
    )
    ->withImportNames(
        importNames: false,
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    )
    ->withPhpSets(
        php84: true   // 8.4
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withSkip([
        RenamePropertyToMatchTypeRector::class,
        \Rector\CodeQuality\Rector\BooleanOr\RepeatedOrEqualToInArrayRector::class,
        \Rector\CodeQuality\Rector\Equal\UseIdenticalOverEqualWithSameTypeRector::class,
        \Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector::class,
        \Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
        \Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector::class,
        \Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector::class,
        \Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector::class,
        \Rector\CodingStyle\Rector\FuncCall\StrictInArrayRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveParentDelegatingConstructorRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector::class,
        \Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector::class,
        \Rector\DeadCode\Rector\MethodCall\RemoveNullNamedArgOnNullDefaultParamRector::class,
        \Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector::class,
        \Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector::class,
        \Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector::class,
        \Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector::class,
        \Rector\Php83\Rector\ClassConst\AddTypeToConstRector::class,
        \Rector\Php84\Rector\Foreach_\ForeachToArrayAnyRector::class,
        \Rector\Php84\Rector\Foreach_\ForeachToArrayFindRector::class,
        \Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector::class,
        \Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\KnownMagicClassMethodTypeRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector::class,
        \Rector\TypeDeclaration\Rector\FuncCall\AddArrayAnyAllClosureParamTypeRector::class,
        \Rector\TypeDeclaration\Rector\FuncCall\AddArrayFunctionClosureParamTypeRector::class,
        \Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector::class,
    ]);
