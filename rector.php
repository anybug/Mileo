<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\CodingStyle\Rector\FunctionLike\FunctionLikeToFirstClassCallableRector;
use Rector\DeadCode\Rector\If_\RemoveUnusedNonEmptyArrayBeforeForeachRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector as CCSelf;
use Rector\DeadCode\Rector\FunctionLike\RemoveDeadReturnRector;
use Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Php70\Rector\MethodCall\ThisCallOnStaticMethodToStaticCallRector;
use Rector\DeadCode\Rector\Assign\RemoveDoubleAssignRector;
use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector as RemoveEmptyMethod;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector as RemoveUnusedPrivate;
use Rector\Php80\Rector\Class_\StringableForToStringRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\SensiolabsSetList;


return static function (RectorConfig $rectorConfig): void {

    // Analyse uniquement TON code
    $rectorConfig->paths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // Migration PHP 7.4 → 8.2 (sans règles dangereuses)
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::DEAD_CODE,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);

    $rectorConfig->skip([

        // ❌ Supprime les cast inutiles — peut changer les types réels - supprime les (int) ou (string)
        RecastingRemovalRector::class,

        // ❌ Supprime les doubles assignations $a = $b = 5 => $a = 5 et $b = 5
        //RemoveDoubleAssignRector::class,

        // ❌ Convertit array() en [] — refactor esthétique seulement 
        //LongArrayToShortArrayRector::class,

        // ❌ Supprime les variables temporaires inutiles — OK mais peut casser du debug
        SimplifyUselessVariableRector::class,

        // ❌ Ajoute automatiquement "implements Stringable" 
        StringableForToStringRector::class,

        // ❌ Supprime @param inutiles
        RemoveUselessParamTagRector::class,

        // ❌ Supprime @return inutiles 
        RemoveUselessReturnTagRector::class,

        // ❌ Supprime les propriétés privées non utilisées — dangereux pour Doctrine
        RemoveUnusedPrivatePropertyRector::class,
        RemoveUnusedPrivate::class,

        // ❌ Supprime les méthodes vides — problématique pour Symfony/Interfaces
        RemoveEmptyClassMethodRector::class,
        RemoveEmptyMethod::class,

        
        // ❌ Convertit $this->method() vers self::method() RECTOR détecte mal
        ThisCallOnStaticMethodToStaticCallRector::class,

        // ❌ Constructor promotion PHP 8 — dangereux pour les Entities Doctrine
        ClassPropertyAssignToConstructorPromotionRector::class,

        // ❌ Ajoute readonly aux propriétés — incompatible avec Doctrine
        ReadOnlyPropertyRector::class,

        // ❌ Supprime les return inutiles — safe mais pas ouf pour le contrôle
        RemoveDeadReturnRector::class,

        // ❌ Convertit les closures en arrow functions — esthétique seulement
        ClosureToArrowFunctionRector::class,

        // ❌ Convertit SomeClass::class → self::class — refactor esthétique
        ClassConstantToSelfClassRector::class,
        CCSelf::class,

        // ❌ Transforme strlen(null) → strlen('') — comportement altéré
        NullToStrictStringFuncCallArgRector::class,

        // ❌ Ajoute "void" aux closures sans return - Peut-être bien pour mieux clarifier
        AddVoidReturnTypeWhereNoReturnRector::class,

        // ❌ Supprime les arrays non utilisés avant foreach — peut masquer des intentions
        RemoveUnusedNonEmptyArrayBeforeForeachRector::class,

        // ❌ Convertit des callable ['class', 'method'] en first-class callable — risky
        FunctionLikeToFirstClassCallableRector::class,
    ]);
};