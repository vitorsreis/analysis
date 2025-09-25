<?php

/**
 * This file is part of the VSR Analysis.
 */

namespace VSR\Analysis\Storage;

use VSR\Analysis\Profiler;

/**
 * @psalm-import-type TData from Profiler
 *
 * @psalm-type TMetricData array{
 *     identifier: string,
 *     type: string,
 *     group: string,
 *     duration: float,
 *     memory_peak: int,
 *     count: int,
 *     profile_id: string|int,
 *     last_duration: float,
 *     last_memory_peak: int,
 *     min_duration: float,
 *     min_memory_peak: int,
 *     max_duration: float,
 *     max_memory_peak: int
 * }
 */
interface StorageInterface
{
    /**
     * @return void
     * @throws
     */
    public function beginTransaction();

    /**
     * @return void
     * @throws // On failure
     */
    public function commitTransaction();

    /**
     * @return void
     * @throws // On failure
     */
    public function rollbackTransaction();

    /**
     * Saves profile info
     *
     * @param TData $data Without key 'profile'
     *
     * @return string|int Identifier of saved profile
     * @throws // On failure
     */
    public function saveProfileInfo($data);

    /**
     * Saves profile entries
     *
     * @psalm-import-type TEntry from Profiler
     *
     * @param string|int $profile_id Identifier of saved profile
     *
     * @param list<TEntry> $entries List of profile entries
     *
     * @return void
     * @throws // On failure
     */
    public function saveProfileEntries($profile_id, $entries);

    /**
     * Saves a profiling metric. Total hits and average of duration and memory peak
     *
     * @param TMetricData $data Metric data
     *
     * @return void
     * @throws // On failure
     */
    public function saveProfileMetric($data);
}
