/*global ajaxurl, ccts_table_shipping_import_params */
;(function ($, window) {

    /**
     * tableShippingImportForm handles the table shipping import process.
     */
    var tableShippingImportForm = function ($form) {
        this.$form = $form;
        this.xhr = false;
        this.mapping = ccts_table_shipping_import_params.mapping;
        this.position = ccts_table_shipping_import_params.start_position || 0;
        this.file = ccts_table_shipping_import_params.file;
        this.update_existing = ccts_table_shipping_import_params.update_existing;
        this.delimiter = ccts_table_shipping_import_params.delimiter;
        this.security = ccts_table_shipping_import_params.import_nonce;
        this.character_encoding = ccts_table_shipping_import_params.character_encoding;
        this.instance_id = ccts_table_shipping_import_params.instance_id;

        // Number of import successes/failures.
        var existing_stats = ccts_table_shipping_import_params.existing_stats || {};
        this.imported = existing_stats.imported || 0;
        this.failed = existing_stats.failed || 0;
        this.updated = existing_stats.updated || 0;
        this.skipped = existing_stats.skipped || 0;

        // Initial state - set progress based on resume status
        var initial_progress = 0;
        if (ccts_table_shipping_import_params.is_resume && this.position > 0) {
            // Calculate rough progress based on file position
            initial_progress = Math.min(90, Math.round(this.position / 1000000 * 100)); // rough estimate
        }
        this.$form.find('.woocommerce-importer-progress').val(initial_progress);

        this.run_import = this.run_import.bind(this);

        // Start importing.
        this.run_import();
    };

    /**
     * Run the import in batches until finished.
     */
    tableShippingImportForm.prototype.run_import = function () {
        var $this = this;

        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'ccts_do_ajax_table_shipping_import',
                position: $this.position,
                mapping: $this.mapping,
                file: $this.file,
                update_existing: $this.update_existing,
                delimiter: $this.delimiter,
                security: $this.security,
                character_encoding: $this.character_encoding,
                instance_id: $this.instance_id
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $this.position = response.data.position;
                    $this.imported = response.data.imported;
                    $this.failed = response.data.failed;
                    $this.updated = response.data.updated;
                    $this.skipped = response.data.skipped;
                    $this.$form.find('.woocommerce-importer-progress').val(response.data.percentage);

                    if ('done' === response.data.position) {
                        var file_name = ccts_table_shipping_import_params.file.split('/').pop();
                        window.location = response.data.url +
                            '&rates-imported=' +
                            '&rates-failed=' +
                            parseInt($this.failed, 10) +
                            '&rates-updated=' +
                            parseInt($this.updated, 10) +
                            '&rates-skipped=' +
                            parseInt($this.skipped, 10) +
                            '&file-name=' +
                            file_name;
                    } else {
                        $this.run_import();
                    }
                } else {
                    // Handle error case
                    if (response.data && response.data.message) {
                        alert('Erro durante a importação: ' + response.data.message);
                    } else {
                        alert('Erro desconhecido durante a importação.');
                    }
                }
            },
            error: function (xhr, status, error) {
                console.log('AJAX Error:', error);
                console.log('Status:', status);
                console.log('Response:', xhr.responseText);

                // Try to restart the import on failure
                if ($this.position > 0) {
                    if (confirm('Ocorreu um erro durante a importação. Deseja tentar continuar de onde parou?')) {
                        setTimeout(function () {
                            $this.run_import();
                        }, 2000);
                    }
                } else {
                    alert('Erro ao iniciar a importação. Verifique o arquivo e tente novamente.');
                }
            }
        });
    };

    /**
     * Function to call tableShippingImportForm on jQuery selector.
     */
    $.fn.ccts_table_shipping_importer = function () {
        new tableShippingImportForm(this);
        return this;
    };

    $('.woocommerce-importer').ccts_table_shipping_importer();

})(jQuery, window);
