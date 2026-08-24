<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

class WPRR_Participant_Analysis_Widget extends \Elementor\Widget_Base
{

    public function get_name()
    {
        return 'wprr_participant_analysis';
    }

    public function get_title()
    {
        return 'Participant Analysis';
    }

    public function get_icon()
    {
        return 'eicon-info-circle';
    }

    public function get_categories()
    {
        return ['wp-race-results'];
    }

    protected function _register_controls()
    {
        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => 'Style',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_bg_color',
            [
                'label' => 'Card Background',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .wprr-analysis-card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => 'Text Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .wprr-analysis-card' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Helper to convert HH:MM:SS or HH:MM:SS.ms to total seconds.
     */
    private function time_to_seconds($time_str)
    {
        if (empty($time_str)) {
            return 0;
        }
        $parts = explode(':', $time_str);
        $seconds = 0;
        if (count($parts) === 3) {
            $seconds += intval($parts[0]) * 3600;
            $seconds += intval($parts[1]) * 60;
            $seconds += floatval($parts[2]);
        } elseif (count($parts) === 2) {
            $seconds += intval($parts[0]) * 60;
            $seconds += floatval($parts[1]);
        }
        return $seconds;
    }

    /**
     * Helper to format seconds back to HH:MM:SS
     */
    private function seconds_to_time($seconds)
    {
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds / 60) % 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
    }

    /**
     * Helper to calculate histogram bins and user position.
     */
    private function calculate_histogram($times, $user_time_seconds, $bins = 15)
    {
        if (empty($times)) {
            return ['labels' => [], 'data' => [], 'userBinIndex' => -1];
        }

        $all_seconds = [];
        foreach ($times as $t) {
            $sec = $this->time_to_seconds($t);
            if ($sec > 0)
                $all_seconds[] = $sec;
        }
        sort($all_seconds);

        if (empty($all_seconds)) {
            return ['labels' => [], 'data' => [], 'userBinIndex' => -1];
        }

        $min_time = min($all_seconds);
        $max_time = max($all_seconds);
        if ($max_time == $min_time)
            $max_time += 60;

        $range = $max_time - $min_time;
        $bin_size = $range / $bins;

        $data = array_fill(0, $bins, 0);
        $labels = [];

        for ($i = 0; $i < $bins; $i++) {
            $start_sec = $min_time + ($i * $bin_size);
            $labels[] = $this->seconds_to_time($start_sec);
        }

        foreach ($all_seconds as $time) {
            $bin = floor(($time - $min_time) / $bin_size);
            if ($bin >= $bins)
                $bin = $bins - 1;
            if ($bin < 0)
                $bin = 0;
            $data[$bin]++;
        }

        $user_bin = floor(($user_time_seconds - $min_time) / $bin_size);
        if ($user_bin >= $bins)
            $user_bin = $bins - 1;
        if ($user_bin < 0)
            $user_bin = 0;

        return [
            'labels' => $labels,
            'data' => $data,
            'userBinIndex' => $user_bin
        ];
    }

    protected function render()
    {
        // 1. Visibility Check
        $entry_id = get_query_var('wprr_entry_id');
        if (empty($entry_id)) {
            return;
        }

        // 2. Fetch Data
        $result = WPRR_DB::get_result_by_id($entry_id);

        if (!$result) {
            echo '<div class="wprr-error">Participant not found.</div>';
            return;
        }

        // Fetch Total Counts
        $total_overall = WPRR_DB::get_total_participants_count($result->event_id, $result->distance);
        $total_gender = WPRR_DB::get_total_participants_count($result->event_id, $result->distance, $result->gender);

        // --- Data Prep for Dual Charts ---

        // 1. Gun Stats
        $rank_gun_overall = $result->rank_overall;
        $rank_gun_gender = $result->rank_gender;
        $passed_gun_overall = max(0, $total_overall - $rank_gun_overall);
        $passed_gun_gender = max(0, $total_gender - $rank_gun_gender);

        // 2. Chip Stats
        $rank_chip_overall = WPRR_DB::get_calculated_rank($result->event_id, $result->distance, $result->chip_time, 'chip_time');
        $rank_chip_gender = WPRR_DB::get_calculated_rank($result->event_id, $result->distance, $result->chip_time, 'chip_time', $result->gender);
        $passed_chip_overall = max(0, $total_overall - $rank_chip_overall);
        $passed_chip_gender = max(0, $total_gender - $rank_chip_gender);

        // --- Histogram Data Prep ---
        $all_race_times = WPRR_DB::get_all_race_times($result->event_id, $result->distance);

        $gun_times = [];
        $chip_times = [];
        foreach ($all_race_times as $rt) {
            if (!empty($rt->gun_time))
                $gun_times[] = $rt->gun_time;
            if (!empty($rt->chip_time))
                $chip_times[] = $rt->chip_time;
        }

        $hist_gun = $this->calculate_histogram($gun_times, $this->time_to_seconds($result->gun_time));
        $hist_chip = $this->calculate_histogram($chip_times, $this->time_to_seconds($result->chip_time));

        // Prepare Data for JS
        $js_data = [
            'performance' => [
                'gun' => [
                    'passedOverall' => (int) $passed_gun_overall,
                    'rankOverall' => (int) $rank_gun_overall,
                    'passedGender' => (int) $passed_gun_gender,
                    'rankGender' => (int) $rank_gun_gender
                ],
                'chip' => [
                    'passedOverall' => (int) $passed_chip_overall,
                    'rankOverall' => (int) $rank_chip_overall,
                    'passedGender' => (int) $passed_chip_gender,
                    'rankGender' => (int) $rank_chip_gender
                ]
            ],
            'distribution' => [
                'gun' => $hist_gun,
                'chip' => $hist_chip
            ],
            'userGender' => esc_js($result->gender)
        ];

        // --- Calculate Percentiles for Display ---
        $gun_percentile = ($total_overall > 0) ? floor(($rank_gun_overall / $total_overall) * 100) : 0;
        $gun_top_text = ($gun_percentile <= 50) ? "TOP " . ($gun_percentile == 0 ? 1 : $gun_percentile) . "%" : "Top " . $gun_percentile . "%";

        $chip_percentile = ($total_overall > 0) ? floor(($rank_chip_overall / $total_overall) * 100) : 0;
        $chip_top_text = ($chip_percentile <= 50) ? "TOP " . ($chip_percentile == 0 ? 1 : $chip_percentile) . "%" : "Top " . $chip_percentile . "%";
        ?>
        <style>
            .wprr-analysis-wrapper {
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
            }

            .wprr-fade-in {
                animation: wprrFadeIn 0.5s ease-out;
            }

            @keyframes wprrFadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .wprr-view-container {
                transition: all 0.3s ease;
            }

            /* Luxury UI Enhancements */
            .wprr-chart-box {
                background: #fff;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
                border-radius: 20px;
                transition: transform 0.3s ease;
                overflow: hidden;
            }

            .wprr-chart-box:hover {
                transform: translateY(-5px);
            }

            /* Responsive Header Layout */
            .wprr-header-left-group {
                display: flex;
                align-items: center;
                gap: 20px;
                flex-wrap: wrap;
                justify-content: flex-start;
                /* Desktop: Align Left */
            }

            .wprr-participant-info {
                text-align: left;
                /* Desktop: Align Left */
            }

            /* New Responsive Layout Classes */
            .wprr-perf-layout {
                display: flex;
                flex-direction: row;
                align-items: center;
                justify-content: center;
                gap: 80px;
                min-height: 320px;
            }

            .wprr-chart-container {
                position: relative;
                width: 320px;
                height: 320px;
                flex-shrink: 0;
            }

            .wprr-stats-column {
                display: flex;
                flex-direction: column;
                gap: 30px;
                border-left: 2px solid #f0f0f0;
                padding-left: 40px;
            }

            .wprr-stat-item {
                text-align: left;
            }

            .wprr-stat-value {
                font-size: 42px;
                font-weight: 800;
                color: #222;
                line-height: 1;
                letter-spacing: -1px;
            }

            .wprr-stat-label {
                font-size: 11px;
                font-weight: 700;
                color: #aaa;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                margin-top: 8px;
            }

            /* Mobile Overrides (Max-width 767px) */
            @media (max-width: 767px) {
                .wprr-chart-box {
                    padding: 25px !important;
                }

                .wprr-chart-header {
                    flex-direction: column;
                    gap: 15px;
                    align-items: center !important;
                    text-align: center;
                }

                .wprr-perf-layout {
                    flex-direction: column-reverse;
                    /* Stats TOP, Chart BOTTOM */
                    gap: 40px;
                }

                .wprr-stats-column {
                    flex-direction: row;
                    border-left: none;
                    padding-left: 0;
                    width: 100%;
                    justify-content: space-around;
                    border-bottom: 1 solid #f0f0f0;
                    padding-bottom: 25px;
                }

                .wprr-stat-item {
                    text-align: center;
                }

                .wprr-chart-container {
                    width: 280px;
                    height: 280px;
                }

                .wprr-stat-value {
                    font-size: 34px;
                }

                /* Mobile Header Adjustments */
                .wprr-header-left-group {
                    justify-content: center;
                    /* Center on Mobile */
                    width: 100%;
                }

                .wprr-participant-info {
                    text-align: center;
                    /* Center on Mobile */
                    width: 100%;
                }
            }
        </style>

        <div class="wprr-analysis-wrapper">

            <div class="wprr-analysis-card"
                style="box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 12px; padding: 30px; margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between; background: #fff;">

                <div class="wprr-header-left-group">
                    <div class="wprr-analysis-rank-wrapper" style="text-align: center; margin-right: 10px;">
                        <div class="wprr-rank-circle"
                            style="background: #333; color: #fff; width: 80px; height: 80px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; margin: 0 auto;">
                            <span style="font-size: 10px; opacity: 0.8; text-transform: uppercase;">Rank</span>
                            <span id="wprr-header-rank"
                                style="font-size: 24px; font-weight: bold; line-height: 1;"><?php echo esc_html($rank_gun_overall); ?></span>
                        </div>
                        <div
                            style="font-size: 11px; text-transform: uppercase; margin-top: 5px; color: #666; font-weight: bold;">
                            OUT OF <?php echo esc_html($total_overall); ?>
                        </div>
                    </div>

                    <div class="wprr-participant-info">
                        <h2 style="margin: 0; font-size: 28px; line-height: 1.2;"><?php echo esc_html($result->full_name); ?>
                        </h2>
                        <div style="opacity: 0.7; font-size: 16px; margin-top: 5px;">
                            Bib: <strong><?php echo esc_html($result->bib_number); ?></strong> &nbsp;|&nbsp;
                            Distance: <strong><?php echo esc_html($result->distance); ?></strong>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; width: 100%; width: auto;">
                    <div
                        style="padding: 15px 25px; background: rgba(0,0,0,0.05); border-radius: 8px; text-align: center; flex: 1; min-width: 120px;">
                        <div style="font-size: 12px; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px;">Chip Time
                        </div>
                        <div style="font-size: 20px; font-weight: bold; color: #333;">
                            <?php echo esc_html($result->chip_time); ?>
                        </div>
                    </div>
                    <div
                        style="padding: 15px 25px; background: rgba(0,0,0,0.05); border-radius: 8px; text-align: center; flex: 1; min-width: 120px;">
                        <div style="font-size: 12px; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px;">Gun Time
                        </div>
                        <div style="font-size: 20px; font-weight: bold; color: #555;"><?php echo esc_html($result->gun_time); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 40px;">

                <div class="wprr-chart-box" style="padding: 40px;">
                    <div class="wprr-chart-header"
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
                        <h4 id="wprr-perf-title"
                            style="margin: 0; font-size: 20px; font-weight: 700; color: #222; letter-spacing: -0.5px;">Gun Time
                            Performance</h4>

                        <div class="wprr-toggle-wrapper"
                            style="background: #f0f2f5; padding: 4px; border-radius: 50px; display: inline-flex; align-items: center;">
                            <button id="wprr-perf-btn-gun" onclick="toggleAnalysisMode('gun')"
                                style="border: none; background: #333; color: #fff; padding: 8px 20px; border-radius: 40px; font-size: 11px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; outline: none; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                                Gun Time
                            </button>
                            <button id="wprr-perf-btn-chip" onclick="toggleAnalysisMode('chip')"
                                style="border: none; background: transparent; color: #888; padding: 8px 20px; border-radius: 40px; font-size: 11px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; outline: none;">
                                Chip Time
                            </button>
                        </div>
                    </div>

                    <div class="wprr-perf-layout">
                        <div id="wprr-perf-view-gun" class="wprr-view-container wprr-fade-in"
                            style="display: flex; flex: 1; align-items: center; justify-content: center; gap: inherit; flex-direction: inherit;">
                            <div class="wprr-chart-container">
                                <canvas id="wprr-perf-chart-gun"
                                    style="position: relative; z-index: 2; width: 100%; height: 100%;"></canvas>
                                <div
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 1; pointer-events: none;">
                                    <div style="font-size: 38px; font-weight: 800; color: #e05a2b; line-height: 1;">
                                        <?php echo esc_html($gun_top_text); ?>
                                    </div>
                                    <div
                                        style="font-size: 12px; font-weight: 600; color: #aaa; text-transform: uppercase; letter-spacing: 2px; margin-top: 8px;">
                                        Percentile</div>
                                </div>
                            </div>
                            <div class="wprr-stats-column">
                                <div class="wprr-stat-item">
                                    <div class="wprr-stat-value"><?php echo esc_html($rank_gun_overall); ?></div>
                                    <div class="wprr-stat-label">Rank Category</div>
                                </div>
                                <div class="wprr-stat-item">
                                    <div class="wprr-stat-value"><?php echo esc_html($rank_gun_gender); ?></div>
                                    <div class="wprr-stat-label">Rank Gender</div>
                                </div>
                            </div>
                        </div>

                        <div id="wprr-perf-view-chip" class="wprr-view-container"
                            style="display: none; flex: 1; align-items: center; justify-content: center; gap: inherit; flex-direction: inherit;">
                            <div class="wprr-chart-container">
                                <canvas id="wprr-perf-chart-chip"
                                    style="position: relative; z-index: 2; width: 100%; height: 100%;"></canvas>
                                <div
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 1; pointer-events: none;">
                                    <div style="font-size: 38px; font-weight: 800; color: #e05a2b; line-height: 1;">
                                        <?php echo esc_html($chip_top_text); ?>
                                    </div>
                                    <div
                                        style="font-size: 12px; font-weight: 600; color: #aaa; text-transform: uppercase; letter-spacing: 2px; margin-top: 8px;">
                                        Percentile</div>
                                </div>
                            </div>
                            <div class="wprr-stats-column">
                                <div class="wprr-stat-item">
                                    <div class="wprr-stat-value"><?php echo esc_html($rank_chip_overall); ?></div>
                                    <div class="wprr-stat-label">Rank Category</div>
                                </div>
                                <div class="wprr-stat-item">
                                    <div class="wprr-stat-value"><?php echo esc_html($rank_chip_gender); ?></div>
                                    <div class="wprr-stat-label">Rank Gender</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wprr-chart-box" style="padding: 30px;">
                    <div class="wprr-chart-header"
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <h4 id="wprr-dist-title" style="margin: 0; font-size: 18px; font-weight: 600; color: #333;">Finishers by
                            Gun Time</h4>

                        <div class="wprr-toggle-wrapper"
                            style="background: #f0f2f5; padding: 4px; border-radius: 50px; display: inline-flex; align-items: center;">
                            <button id="wprr-dist-btn-gun" onclick="toggleDistMode('gun')"
                                style="border: none; background: #333; color: #fff; padding: 8px 20px; border-radius: 40px; font-size: 11px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; outline: none; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                                Gun Time
                            </button>
                            <button id="wprr-dist-btn-chip" onclick="toggleDistMode('chip')"
                                style="border: none; background: transparent; color: #888; padding: 8px 20px; border-radius: 40px; font-size: 11px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; outline: none;">
                                Chip Time
                            </button>
                        </div>
                    </div>

                    <div style="position: relative; height: 350px;">
                        <div id="wprr-dist-view-gun" class="wprr-view-container wprr-fade-in" style="height: 100%;">
                            <canvas id="wprr-dist-chart-gun"></canvas>
                        </div>
                        <div id="wprr-dist-view-chip" class="wprr-view-container" style="display: none; height: 100%;">
                            <canvas id="wprr-dist-chart-chip"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top: 50px; text-align: center;">
                <a href="javascript:history.back()"
                    style="display: inline-block; padding: 14px 35px; border: 2px solid #333; border-radius: 12px; color: #333; text-decoration: none; font-weight: 700; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px rgba(0,0,0,0.05);"
                    onmouseover="this.style.background='#333';this.style.color='#fff';this.style.transform='scale(1.05)';"
                    onmouseout="this.style.background='transparent';this.style.color='#333';this.style.transform='scale(1)';">&larr;
                    Back to Full Results</a>
            </div>

        </div>

        <script>
            (function () {
                const data = <?php echo json_encode($js_data); ?>;
                const userGender = data.userGender;

                // --- Shared Performance Chart Config ---
                const getPerfConfig = (mode) => ({
                    type: 'doughnut',
                    data: {
                        labels: ['Slower', 'Rank'],
                        datasets: [
                            {
                                data: [data.performance[mode].passedOverall, data.performance[mode].rankOverall],
                                backgroundColor: ['#e05a2b', '#f3f3f3'],
                                borderWidth: 0,
                                borderRadius: 20,
                                hoverOffset: 15,
                                cutout: '80%'
                            },
                            {
                                data: [data.performance[mode].passedGender, data.performance[mode].rankGender],
                                backgroundColor: ['#c4451b', '#e8e8e8'],
                                borderWidth: 0,
                                borderRadius: 20,
                                hoverOffset: 10,
                                cutout: '60%'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: 20 },
                        animation: { animateScale: true, animateRotate: true, duration: 1800, easing: 'easeOutElastic' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#222',
                                padding: 15,
                                cornerRadius: 10,
                                titleFont: { size: 0 },
                                bodyFont: { size: 13, family: "'Inter', sans-serif", weight: '600' },
                                callbacks: {
                                    label: function (context) {
                                        const ds = context.datasetIndex;
                                        const idx = context.dataIndex;
                                        const val = context.raw;
                                        if (ds === 0) {
                                            return (idx === 0) ? ' Finished after you: ' + val : ' Rank Category: ' + val;
                                        } else {
                                            return (idx === 0) ? ' ' + userGender + 's after you: ' + val : ' Rank ' + userGender + ': ' + val;
                                        }
                                    }
                                }
                            }
                        }
                    }
                });

                // --- Shared Distribution Chart Config ---
                const getDistConfig = (mode) => {
                    const dist = data.distribution[mode];
                    const bgColors = dist.data.map((_, i) => i === dist.userBinIndex ? '#e05a2b' : '#333333');

                    return {
                        type: 'bar',
                        data: {
                            labels: dist.labels,
                            datasets: [{
                                label: 'Participants',
                                data: dist.data,
                                backgroundColor: bgColors,
                                borderRadius: 5,
                                barPercentage: 0.8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1, precision: 0 },
                                    grid: { borderDash: [2, 4], color: '#f0f0f0' }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { maxRotation: 45, minRotation: 45, font: { size: 10 } }
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    callbacks: {
                                        title: (tooltipItems) => 'Time Range: ' + tooltipItems[0].label,
                                        label: (context) => 'Participants: ' + context.raw
                                    }
                                }
                            }
                        }
                    };
                };

                // --- Initialization ---
                document.addEventListener('DOMContentLoaded', () => {
                    // Init Performance Charts
                    const ctxPerfGun = document.getElementById('wprr-perf-chart-gun').getContext('2d');
                    new Chart(ctxPerfGun, getPerfConfig('gun'));

                    // Init Distribution Charts
                    const ctxDistGun = document.getElementById('wprr-dist-chart-gun').getContext('2d');
                    new Chart(ctxDistGun, getDistConfig('gun'));
                });

                // --- Global Toggles (Standardized Padding: 8px 20px) ---

                // UNIFORM STYLE CONSTANTS (Applicable to both toggles)
                const activeStyle = "border: none; background: #333; color: #fff; padding: 8px 20px; border-radius: 40px; font-size: 11px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; outline: none; box-shadow: 0 4px 10px rgba(0,0,0,0.15);";
                const inactiveStyle = "border: none; background: transparent; color: #888; padding: 8px 20px; border-radius: 40px; font-size: 11px; font-weight: 700; text-transform: uppercase; cursor: pointer; transition: all 0.3s ease; outline: none;";

                window.toggleAnalysisMode = function (mode) {
                    const gunView = document.getElementById('wprr-perf-view-gun');
                    const chipView = document.getElementById('wprr-perf-view-chip');
                    const title = document.getElementById('wprr-perf-title');
                    const headerRank = document.getElementById('wprr-header-rank');
                    const btnGun = document.getElementById('wprr-perf-btn-gun');
                    const btnChip = document.getElementById('wprr-perf-btn-chip');

                    if (mode === 'gun') {
                        chipView.style.display = 'none';
                        gunView.style.display = 'flex';
                        gunView.classList.add('wprr-fade-in');
                        title.innerText = 'Gun Time Performance';
                        headerRank.innerText = data.performance.gun.rankOverall;
                        btnGun.style.cssText = activeStyle;
                        btnChip.style.cssText = inactiveStyle;
                    } else {
                        gunView.style.display = 'none';
                        chipView.style.display = 'flex';
                        chipView.classList.add('wprr-fade-in');
                        title.innerText = 'Chip Time Performance';
                        headerRank.innerText = data.performance.chip.rankOverall;
                        btnGun.style.cssText = inactiveStyle;
                        btnChip.style.cssText = activeStyle;

                        if (!window.perfChartChip) {
                            const ctxPerfChip = document.getElementById('wprr-perf-chart-chip').getContext('2d');
                            window.perfChartChip = new Chart(ctxPerfChip, getPerfConfig('chip'));
                        }
                    }
                };

                window.toggleDistMode = function (mode) {
                    const gunView = document.getElementById('wprr-dist-view-gun');
                    const chipView = document.getElementById('wprr-dist-view-chip');
                    const title = document.getElementById('wprr-dist-title');
                    const btnGun = document.getElementById('wprr-dist-btn-gun');
                    const btnChip = document.getElementById('wprr-dist-btn-chip');

                    if (mode === 'gun') {
                        chipView.style.display = 'none';
                        gunView.style.display = 'block';
                        gunView.classList.add('wprr-fade-in');
                        title.innerText = 'Finishers by Gun Time';
                        btnGun.style.cssText = activeStyle;
                        btnChip.style.cssText = inactiveStyle;
                    } else {
                        gunView.style.display = 'none';
                        chipView.style.display = 'block';
                        chipView.classList.add('wprr-fade-in');
                        title.innerText = 'Finishers by Chip Time';
                        btnGun.style.cssText = inactiveStyle;
                        btnChip.style.cssText = activeStyle;

                        if (!window.distChartChip) {
                            const ctxDistChip = document.getElementById('wprr-dist-chart-chip').getContext('2d');
                            window.distChartChip = new Chart(ctxDistChip, getDistConfig('chip'));
                        }
                    }
                };
            })();
        </script>
        <?php
    }
}
