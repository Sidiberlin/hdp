import Popup from "./Popup";

export default class ExportPopup extends Popup {

	public static readonly EXPORT: string = "pdf";

	protected dialog: HTMLElement;

	protected closeButton: HTMLButtonElement;

	protected pdfExportOption: HTMLElement;

	protected odfExportOption: HTMLElement;

	public constructor( appendTo: HTMLElement, expandButton: HTMLButtonElement ) {
		super( '', appendTo, expandButton );
	}

	protected buildHeader(): HTMLElement {
		const headerCnt = document.createElement( 'div' );
		headerCnt.classList.add( 'popup-header-cnt' );
		const headerText = document.createElement( 'h4' );
		headerText.textContent = mw.message( 'chat-export-popup-title' ).plain();
		headerCnt.appendChild( headerText );
		return headerCnt;
	}

	protected buildContent(): HTMLElement {
		const contentCnt = document.createElement( 'div' );
		contentCnt.classList.add( 'popup-body-cnt' );

		const contentOptionsCnt = document.createElement( 'div' );
		contentOptionsCnt.classList.add( 'popup-format-export-cnt' );
		const contentText = document.createElement( 'p' );
		contentText.classList.add( 'popup-format-label' );
		contentText.textContent = mw.message( 'chat-export-popup-format-label' ).plain() + ':';
		contentOptionsCnt.appendChild( contentText );

		const radioGroup = this.buildRadioBtn();
		contentOptionsCnt.appendChild( radioGroup );

		contentCnt.appendChild( contentOptionsCnt );

		const contentBtnCnt = document.createElement( 'div' );
		contentBtnCnt.classList.add( 'popup-btn-export-cnt' );
		const exportBtn = document.createElement( 'button' );
		exportBtn.classList.add( 'popup-btn' );
		exportBtn.textContent = mw.message( 'chat-export-popup-export-button' ).plain();
		exportBtn.addEventListener( 'click', this.onExport.bind( this ) );
		contentBtnCnt.appendChild( exportBtn );

		const cancelBtn = document.createElement( 'button' );
		cancelBtn.classList.add( 'popup-btn' );
		cancelBtn.textContent = mw.message( 'chat-export-popup-cancel-button' ).plain();
		cancelBtn.addEventListener( 'click', this.hide.bind( this ) );
		contentBtnCnt.appendChild( cancelBtn );

		contentCnt.appendChild( contentBtnCnt );
		return contentCnt;
	}

	private onExport(): void {
		const selectedExport = document.querySelector( 'input[name = "export"]:checked' ) as HTMLInputElement;
		const exportType = selectedExport.value;
		this.hide();
		this.emit( ExportPopup.EXPORT, exportType );
	}

	private buildRadioBtn(): HTMLElement {
		const radioDiv = document.createElement( 'div' );
		radioDiv.classList.add( 'radio-btn' );

		this.pdfExportOption = this.buildRadioInput( 'pdf', true );
		radioDiv.appendChild( this.pdfExportOption );
		const pdfExportLabel = this.buildRadioLabel( 'pdf', 'PDF' );
		radioDiv.appendChild( pdfExportLabel );

		this.odfExportOption = this.buildRadioInput( 'odf', false );
		radioDiv.appendChild( this.odfExportOption );
		const odfExportLabel = this.buildRadioLabel( 'odf', 'DOCX' );
		radioDiv.appendChild( odfExportLabel );

		return radioDiv;
	}

	private buildRadioLabel( id: string, label: string ): HTMLElement {
		const exportRadioLabel = document.createElement( 'label' );
		exportRadioLabel.setAttribute( 'for', id );
		exportRadioLabel.textContent = label;
		return exportRadioLabel;
	}

	private buildRadioInput( id: string, checked: boolean ): HTMLInputElement {
		const exportRadio = document.createElement( 'input' );
		exportRadio.setAttribute( 'type', 'radio' );
		exportRadio.setAttribute( 'name', 'export' );
		exportRadio.setAttribute( 'value', id );
		if ( checked ) {
			exportRadio.setAttribute( 'checked', '' );
		}
		exportRadio.setAttribute( 'id', id );
		return exportRadio;
	}

}
