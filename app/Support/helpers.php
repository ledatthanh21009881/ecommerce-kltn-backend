<?php

/**
 * Get storage path
 */
function storage_path(string $path = ''): string
{
    $basePath = __DIR__ . '/../../storage';
    return $basePath . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Get public path
 */
function public_path(string $path = ''): string
{
    $basePath = __DIR__ . '/../../public';
    return $basePath . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Get base path
 */
function base_path(string $path = ''): string
{
    $basePath = __DIR__ . '/../../';
    return $basePath . ($path ? '/' . ltrim($path, '/') : '');
}
