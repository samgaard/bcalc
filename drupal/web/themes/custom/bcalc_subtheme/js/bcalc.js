/**
 * @file
 * JavaScript integration between Highcharts and Drupal.
 */
(function ($) {
    'use strict';

    Drupal.behaviors.bcalc = {
        attach: function (context, settings) {

            $('.charts-highchart').once().each(function () {
                if ($(this).attr('data-chart')) {
                    var highcharts = $(this).attr('data-chart');
                    var hc = JSON.parse(highcharts);
                    if (hc.chart.type === 'pie') {
                        delete hc.plotOptions.bar;
                        hc.plotOptions.pie = {
                            allowPointSelect: true,
                            cursor: 'pointer',
                            showInLegend: true,
                            dataLabels: {
                                enabled: true,
                                format: '<b>{point.name}</b><br>${point.y} ({point.percentage:.0f}%)'
                            }
                        };

                        hc.legend.enabled = false;
                        hc.legend.labelFormatter = function () {
                            var legendIndex = this.index;
                            return this.series.chart.axes[0].categories[legendIndex];
                        };

                        hc.tooltip.formatter = function () {
                            var sliceIndex = this.point.index;
                            var sliceName = this.series.chart.axes[0].categories[sliceIndex];
                            return '' + sliceName +
                                ' : $' + this.y + ' (' + this.percentage.toFixed(0) + '%)';
                        };

                        console.log(hc);
                    }

                    $(this).highcharts(hc);
                }
            });
            var year = 0;
            var dateChanger = $('#date-changer');
            if (year = get('year')) {
                dateChanger.val(year);
            }
            dateChanger.on('change', function () {
                window.location = '?year=' + $(this).val();
            })

        }
    };

    function get(name) {
        if (name = (new RegExp('[?&]' + encodeURIComponent(name) + '=([^&]*)')).exec(location.search))
            return decodeURIComponent(name[1]);
    }

}(jQuery));
