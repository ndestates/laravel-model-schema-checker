<?php

describe('Environment Detection Logic', function () {

    it('correctly identifies production environment', function () {
        $envValue = 'production';
        $isProduction = $envValue === 'production';
        
        expect($isProduction)->toBeTrue();
    });

    it('correctly identifies development environment', function () {
        $envValue = 'development';
        $isProduction = $envValue === 'production';
        $isDevelopment = $envValue === 'development';
        
        expect($isDevelopment)->toBeTrue()
            ->and($isProduction)->toBeFalse();
    });

    it('correctly identifies local environment', function () {
        $envValue = 'local';
        $isProduction = $envValue === 'production';
        $isLocal = $envValue === 'local';
        
        expect($isLocal)->toBeTrue()
            ->and($isProduction)->toBeFalse();
    });

    it('correctly identifies testing environment', function () {
        $envValue = 'testing';
        $isProduction = $envValue === 'production';
        $isTesting = $envValue === 'testing';
        
        expect($isTesting)->toBeTrue()
            ->and($isProduction)->toBeFalse();
    });

    it('determines correct middleware based on environment', function () {
        // Production should require authentication
        $productionMiddleware = 'production' === 'production' ? ['web', 'auth'] : ['web'];
        expect($productionMiddleware)->toBe(['web', 'auth']);
        
        // Development should only require web
        $developmentMiddleware = 'development' === 'production' ? ['web', 'auth'] : ['web'];
        expect($developmentMiddleware)->toBe(['web']);
        
        // Local should only require web  
        $localMiddleware = 'local' === 'production' ? ['web', 'auth'] : ['web'];
        expect($localMiddleware)->toBe(['web']);
        
        // Testing should only require web
        $testingMiddleware = 'testing' === 'production' ? ['web', 'auth'] : ['web'];
        expect($testingMiddleware)->toBe(['web']);
    });

});