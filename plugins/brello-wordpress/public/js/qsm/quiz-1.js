(function( $ ) {
	'use strict';
	var weight_chart;
	var graphic_page;

	var currentWeight,idealWeight =0;
	function calculateValues(){
		updateData();
		let val1	=	Math.round(currentWeight-7);
		let val2	=	idealWeight + Math.round(7);
		let data	=	[currentWeight, val1, val2, idealWeight];
		return data;
	}
	function init_chart(){	
		if(weight_chart){
			weight_chart.destroy();
		}

		let labels  	=	['Today', '', '', 'Goal'];
		let values  	=	calculateValues();
		const minValue	=	Math.min(...values);
		const maxValue	=	Math.max(...values);

		const newValues	=	[values[0]+1, ...values, values[values.length-1]-1];
		const newLabels	=	['', ...labels, ''];

		const ctx		=	document.getElementById('graph_canvas').getContext('2d');
		const gradient 	=	ctx.createLinearGradient(0, 0, 700, 0);
		gradient.addColorStop(0, '#1d005d');
		gradient.addColorStop(0.5, '#1d005d');
		gradient.addColorStop(1, '#b4add3');

        const data = {
            labels: newLabels,
            datasets: [{
                label: 'Dataset 1',
                data: newValues,
                borderColor: '#967CFF',
                backgroundColor: gradient,
				tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#967CFF',
                pointBorderWidth: 3,
                pointRadius: (ctx) => (
					ctx.dataIndex === 0 || ctx.dataIndex === newValues.length - 1 
					? 0
					: 10
				),
            }]
        };

        const options = {
            responsive: true,
            plugins: {
                tooltip: {
                    enabled: false
                },
                legend: {
                    display: false
                },
                datalabels: {
                    anchor: 'end', // Posición del valor respecto al punto
                    align: 'right', // Alinear encima del punto
                    backgroundColor: '#ffffff', // Fondo blanco con opacidad
                    borderColor: '#967CFF', // Borde similar al color del gráfico
                    borderWidth:  (value) => {
						if(value.dataIndex === 0 || value.dataIndex === newValues.length-1){
							return 0;
						}
						return 1;
					},
                    borderRadius: 5,
                    color: '#000000',
                    font: {
                        /*weight: 'bold',*/
                        size: 14
                    },
                    padding: (value) => {
						if(value.dataIndex === 0 || value.dataIndex === newValues.length-1){
							return 0;
						}
						return 5;
					},
                    formatter: (value, ctx) => {
						if(ctx.dataIndex === 0 || ctx.dataIndex === newValues.length-1){
							return '';
						}
						return value + ' lbs.';
					},
                    offset: 20
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
					ticks: {
						color: '#ffffff',
						font: {
							size: 14
						}
					}
					/*offset: true*/
                },
                y: {
                    beginAtZero: true,
					min: minValue - 10,
					max: maxValue + 10,
					ticks: {
						display:false,
						color: '#ffffff',
						font: {
							size: 14
						}
					}
                }
            }
        };

        Chart.register(ChartDataLabels);		

        weight_chart = new Chart(ctx, {
            type: 'line',
            data: data,
            options: options,
            plugins: [ChartDataLabels]
        });

	}
	function updateData(){
		currentWeight	=	parseInt($('.qsm-page.current-weight input').val() || 0);
		idealWeight		=	parseInt($('.qsm-page.ideal-weight input').val() || 0);
	}

	jQuery(document).on('qsm_init_progressbar_before', function(event, quizID, qmn_quiz_data) {
		jQuery.each(qmn_quiz_data[quizID].qpages, function (numberQpage, qpage) {
			if(qpage.pagekey=='page-with-graphic')
				graphic_page	=	parseInt(numberQpage) - 1;
		});
	});

	jQuery(document).on('qsm_go_to_page_after', function(event, quizID, pageNumber) {
		let current_page = jQuery('#quizForm' + quizID).find('.current_page_hidden').val();
		if(graphic_page==current_page){
			jQuery('body').addClass('quiz-display-graph');
			setTimeout(() => {
				init_chart();	
			}, 100);
			
		}else{
			jQuery('body').removeClass('quiz-display-graph');
		}
	});

	$('body').on('change', '.qmn_quiz_container select, .qmn_quiz_container textarea, .qmn_quiz_container input', function () {
		try {
			updateData();
		} catch (error) {
			console.error("Error actualizando en tiempo real:", error);
		}
	});

})( jQuery );