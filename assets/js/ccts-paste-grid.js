/* global cctsPasteParams, jQuery */
/**
 * Cole sua planilha aqui — grade de colagem para o Frete Offline (Claude Code).
 *
 * Distribui o conteúdo colado (Excel/Sheets/TSV/CSV) nas células, permite
 * edição manual e, ao processar, envia as linhas via AJAX. O servidor gera um
 * CSV anexado e devolve o ID, que é plugado no mesmo campo "table_rate" usado
 * pelo upload — em seguida o formulário é salvo automaticamente.
 */
( function ( $ ) {
	'use strict';

	var COLS = 7; // CEP ini, CEP fim, Peso ini, Peso fim, Custo, Prazo, Nome.

	$( function () {
		var $grid = $( '#ccts-paste-grid' );

		if ( ! $grid.length || 'undefined' === typeof cctsPasteParams ) {
			return;
		}

		var $tbody    = $grid.find( 'tbody' );
		var $count    = $( '#ccts-paste-count' );
		var $feedback = $( '#ccts-paste-feedback' );

		/**
		 * Build a single empty row matching the header layout.
		 */
		function buildRow() {
			var $tr = $( '<tr/>' );
			$tr.append( '<td class="ccts-paste-rownum"></td>' );
			for ( var c = 0; c < COLS; c++ ) {
				$tr.append(
					$( '<td/>' ).append(
						$( '<input type="text" spellcheck="false" autocomplete="off" class="ccts-paste-cell"/>' )
							.attr( 'data-col', c )
					)
				);
			}
			$tr.append( '<td class="ccts-paste-rowdel"><button type="button" class="ccts-paste-del" title="Remover linha">&times;</button></td>' );
			return $tr;
		}

		/**
		 * Renumber rows, refresh data-row attributes and the filled counter.
		 */
		function refresh() {
			var filled = 0;
			$tbody.find( 'tr' ).each( function ( i ) {
				var $tr = $( this );
				$tr.find( '.ccts-paste-rownum' ).text( i + 1 );
				var hasValue = false;
				$tr.find( '.ccts-paste-cell' ).each( function ( c ) {
					$( this ).attr( 'data-row', i ).attr( 'data-col', c );
					if ( '' !== $.trim( this.value ) ) {
						hasValue = true;
					}
				} );
				if ( hasValue ) {
					filled++;
				}
			} );

			var label = $count.data( 'label' ) || 'linha(s) preenchida(s)';
			// Keep the suffix text already rendered server-side.
			$count.text( filled + ' ' + ( $count.text().replace( /^\d+\s*/, '' ) || label ) );
		}

		function ensureRows( needed ) {
			var current = $tbody.find( 'tr' ).length;
			for ( var i = current; i < needed; i++ ) {
				$tbody.append( buildRow() );
			}
		}

		function cellAt( row, col ) {
			return $tbody.find( '.ccts-paste-cell[data-row="' + row + '"][data-col="' + col + '"]' );
		}

		/**
		 * Parse clipboard text into a 2D matrix.
		 * Splits rows by newline and columns by tab; falls back to comma/semicolon
		 * when there are no tabs (single-column CSV paste).
		 */
		function parseClipboard( text ) {
			text = text.replace( /\r\n/g, '\n' ).replace( /\r/g, '\n' );
			var lines = text.split( '\n' );

			// Drop a trailing empty line produced by a final newline.
			while ( lines.length && '' === $.trim( lines[ lines.length - 1 ] ) ) {
				lines.pop();
			}

			var hasTab = text.indexOf( '\t' ) !== -1;

			return lines.map( function ( line ) {
				if ( hasTab ) {
					return line.split( '\t' );
				}
				if ( line.indexOf( ';' ) !== -1 && line.indexOf( ',' ) === -1 ) {
					return line.split( ';' );
				}
				return line.split( ',' );
			} );
		}

		// --- Paste handler -------------------------------------------------
		$tbody.on( 'paste', '.ccts-paste-cell', function ( e ) {
			var clip = ( e.originalEvent || e ).clipboardData || window.clipboardData;
			if ( ! clip ) {
				return; // Let the browser handle it.
			}

			var text = clip.getData( 'text' );
			if ( ! text || ( text.indexOf( '\t' ) === -1 && text.indexOf( '\n' ) === -1 && text.indexOf( ';' ) === -1 && text.indexOf( ',' ) === -1 ) ) {
				// Plain single value — default paste into the focused cell.
				return;
			}

			e.preventDefault();

			var matrix    = parseClipboard( text );
			var startRow  = parseInt( $( this ).attr( 'data-row' ), 10 ) || 0;
			var startCol  = parseInt( $( this ).attr( 'data-col' ), 10 ) || 0;

			ensureRows( startRow + matrix.length );

			matrix.forEach( function ( cells, r ) {
				cells.forEach( function ( value, c ) {
					var col = startCol + c;
					if ( col >= COLS ) {
						return;
					}
					var $cell = cellAt( startRow + r, col );
					if ( $cell.length ) {
						$cell.val( $.trim( value ) );
					}
				} );
			} );

			refresh();
		} );

		// --- Manual edits keep the counter live ---------------------------
		$tbody.on( 'input', '.ccts-paste-cell', refresh );

		// --- Remove a row --------------------------------------------------
		$tbody.on( 'click', '.ccts-paste-del', function () {
			if ( $tbody.find( 'tr' ).length > 1 ) {
				$( this ).closest( 'tr' ).remove();
			} else {
				$( this ).closest( 'tr' ).find( '.ccts-paste-cell' ).val( '' );
			}
			refresh();
		} );

		// --- Add row -------------------------------------------------------
		$( '#ccts-paste-add-row' ).on( 'click', function () {
			$tbody.append( buildRow() );
			refresh();
		} );

		// --- Clear grid ----------------------------------------------------
		$( '#ccts-paste-clear' ).on( 'click', function () {
			$tbody.find( '.ccts-paste-cell' ).val( '' );
			$feedback.hide().removeClass( 'ccts-ok ccts-err' ).text( '' );
			refresh();
		} );

		function showFeedback( message, ok ) {
			$feedback
				.removeClass( 'ccts-ok ccts-err' )
				.addClass( ok ? 'ccts-ok' : 'ccts-err' )
				.text( message )
				.show();
		}

		/**
		 * Collect non-empty rows as arrays of COLS strings.
		 */
		function collectRows() {
			var rows = [];
			$tbody.find( 'tr' ).each( function () {
				var values = [];
				var filled = false;
				$( this ).find( '.ccts-paste-cell' ).each( function () {
					var v = $.trim( this.value );
					values.push( v );
					if ( '' !== v ) {
						filled = true;
					}
				} );
				if ( filled ) {
					rows.push( values );
				}
			} );
			return rows;
		}

		// --- Process -------------------------------------------------------
		$( '#ccts-paste-process' ).on( 'click', function () {
			var $btn = $( this );
			var rows = collectRows();

			// Essentials: CEP inicial (0), CEP final (1), custo (4).
			var valid = rows.filter( function ( r ) {
				return '' !== r[ 0 ] && '' !== r[ 1 ] && '' !== r[ 4 ];
			} );

			if ( ! valid.length ) {
				showFeedback( cctsPasteParams.i18nEmpty, false );
				return;
			}

			$btn.prop( 'disabled', true );
			showFeedback( cctsPasteParams.i18nDone, true );

			$.ajax( {
				url:      cctsPasteParams.ajaxUrl,
				method:   'POST',
				dataType: 'json',
				data:     {
					action: 'ccts_paste_to_csv',
					nonce:  cctsPasteParams.nonce,
					rows:   JSON.stringify( valid )
				}
			} ).done( function ( response ) {
				if ( ! response || ! response.success || ! response.data || ! response.data.id ) {
					var msg = ( response && response.data && response.data.message ) ? response.data.message : cctsPasteParams.i18nError;
					showFeedback( msg, false );
					$btn.prop( 'disabled', false );
					return;
				}

				var data = response.data;

				// Plug the generated attachment into the same field the upload uses.
				$( '#' + cctsPasteParams.fieldKey ).val( data.id );

				// Mirror admin.js: reveal the file card and save.
				$( '.ccts-upload-wrapper' ).addClass( 'hidden' );
				var $uploader = $( '.ccts-file-uploader' );
				if ( $uploader.length ) {
					$uploader.removeClass( 'hidden' );
					$uploader.find( '[data-name="title"]' ).text( 'Tabela de frete (colada)' );
					$uploader.find( '[data-name="filename"]' ).text( data.filename ).attr( 'href', data.url );
					$uploader.find( '[data-name="filesize"]' ).text( data.filesize );
				}

				showFeedback( ( data.count || valid.length ) + ' linha(s) importada(s). Salvando...', true );

				// Trigger the WooCommerce settings save (same as the upload flow).
				$( '.woocommerce-save-button' ).trigger( 'click' );
			} ).fail( function () {
				showFeedback( cctsPasteParams.i18nError, false );
				$btn.prop( 'disabled', false );
			} );
		} );

		refresh();
	} );
}( jQuery ) );
