$(document).ready(function () {
    if (!$('#activity_log_table').length) {
        return;
    }

    var today = new Date().toLocaleDateString('es-CO');
    var todayISO = new Date().toISOString().slice(0, 10);

    var table = $('#activity_log_table').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: APP_URL + '/activity-logs/data',
            type: 'GET',
            data: function (d) {
                d.event = $('#filter-event').val();
                d.user_id = $('#filter-user').val();
                d.date_from = $('#filter-date-from').val();
                d.date_to = $('#filter-date-to').val();
            }
        },
        columns: [
            { title: 'ID' },
            { title: 'Fecha' },
            { title: 'Evento', createdCell: function(td) { td.classList.add('text-center'); } },
            { title: 'Descripción' },
            { title: 'Usuario' },
            { title: 'IP' },
        ],
        order: [[1, 'desc']],
        searching: false,
        pageLength: 25,
        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],
        responsive: true,
        autoWidth: false,
        dom: '<"mb-2"l>Bfrtip',
        buttons: [
            {
                extend: 'collection',
                text: 'Reportes',
                titleAttr: 'Exportar registro de auditoría',
                attr: { 'aria-label': 'Exportar registro de auditoría' },
                orientation: 'landscape',
                buttons: [
                    {
                        text: 'Copiar',
                        extend: 'copy'
                    },
                    {
                        extend: 'pdf',
                        title: 'Audit Log - SecureAuth',
                        filename: 'audit_log_' + todayISO,
                        pageSize: 'LETTER',
                        customize: function (doc) {
                            doc.defaultStyle.fontSize = 10;
                            doc.styles.tableHeader.fontSize = 11;
                            doc.styles.tableHeader.fillColor = '#142e3d';
                            doc.styles.tableHeader.color = '#ffffff';

                            doc.content.splice(0, 1, {
                                text: 'AUDIT LOG - SECUREAUTH',
                                style: {
                                    fontSize: 16,
                                    alignment: 'center',
                                    bold: true,
                                    margin: [0, 10, 0, 10]
                                }
                            });

                            doc.content.splice(1, 0, {
                                text: 'Registro de actividad del sistema',
                                style: {
                                    fontSize: 11,
                                    alignment: 'center',
                                    italic: true,
                                    margin: [0, 0, 0, 10]
                                }
                            });

                            doc.content.splice(2, 0, {
                                text: 'Generado el: ' + new Date().toLocaleString('es-CO'),
                                style: {
                                    fontSize: 9,
                                    alignment: 'right',
                                    margin: [0, 0, 0, 10]
                                }
                            });

                            doc.footer = function (currentPage, pageCount) {
                                return {
                                    columns: [
                                        { text: 'SecureAuth', alignment: 'left', fontSize: 8 },
                                        { text: 'Página ' + currentPage + ' de ' + pageCount, alignment: 'center', fontSize: 8 },
                                        { text: 'Confidencial', alignment: 'right', fontSize: 8 }
                                    ],
                                    margin: [40, 0]
                                };
                            };
                        }
                    },
                    {
                        extend: 'excel',
                        title: 'Audit Log - SecureAuth',
                        filename: 'audit_log_' + todayISO,
                        messageTop: 'Registro de actividad del sistema',
                        messageBottom: 'Documento generado el ' + today
                    },
                    {
                        extend: 'csv',
                        filename: 'audit_log_' + todayISO
                    },
                    {
                        extend: 'print',
                        text: 'Imprimir',
                        title: 'Audit Log - SecureAuth',
                        messageTop: 'Reporte generado el ' + today,
                        customize: function (win) {
                            $(win.document.body).find('table')
                                .addClass('table-striped')
                                .css('font-size', '12px');
                        }
                    }
                ]
            },
            {
                extend: 'colvis',
                text: 'Columnas',
                titleAttr: 'Mostrar u ocultar columnas',
                attr: { 'aria-label': 'Mostrar u ocultar columnas' }
            }
        ],
        initComplete: function () {
            $(this.api().table().node()).css('visibility', 'visible');
        },
        drawCallback: function (settings) {
            var total = settings.json ? settings.json.recordsFiltered : 0;
            $('#activity_log_status').text(total + ' eventos encontrados.');
        }
    });

    table.buttons().container().appendTo('#activity_log_table_wrapper .col-md-6:eq(0)');

    var $btnFilter = $('#btn-filter');
    var $btnFilterLabel = $btnFilter.find('.btn-filter-label');
    var btnFilterOriginalLabel = $btnFilterLabel.text();

    $btnFilter.on('click', function () {
        table.ajax.reload();
    });

    $('#activity_log_table').on('processing.dt', function (e, settings, processing) {
        $btnFilter.prop('disabled', processing).attr('aria-busy', processing);
        $btnFilterLabel.text(processing ? 'Filtrando…' : btnFilterOriginalLabel);
    });

    $('.js-filters-toggle').on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).click();
        }
    });

    $('#filtersCollapse')
        .on('shown.bs.collapse', function () {
            $('.js-filters-toggle').attr('aria-expanded', 'true');
        })
        .on('hidden.bs.collapse', function () {
            $('.js-filters-toggle').attr('aria-expanded', 'false');
        });
});
