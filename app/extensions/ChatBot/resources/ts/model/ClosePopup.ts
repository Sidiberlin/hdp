import Popup from "./Popup";

declare const mw: any;

export default class ClosePopup extends Popup {

	public static readonly EXPORT: string = "export";

	public static readonly CLOSE: string = "close";

	private exportBtn: HTMLButtonElement;

	public constructor( appendTo: HTMLElement, expandButton: HTMLButtonElement, disableExport: boolean = false ) {
		super( '', appendTo, expandButton );
		this.dialog.classList.add( 'popup-chatbot-close-cnt' );

		if ( disableExport ) {
			this.disableExportButton();
		}
	}

	protected buildHeader(): HTMLElement {
		const headerCnt = document.createElement( 'div' );
		headerCnt.classList.add( 'popup-header-cnt' );
		const headerText = document.createElement( 'h4' );
		headerText.textContent = mw.message( 'chat-close-popup-header' ).text()
		headerCnt.appendChild( headerText );
		return headerCnt;
	}

	protected buildContent(): HTMLElement {
		const contentCnt = document.createElement( 'div' );
		contentCnt.classList.add( 'popup-body-close-cnt' );

		const contentOptionsCnt = document.createElement( 'div' );
		contentOptionsCnt.classList.add( 'popup-content-close-cnt' );
		const contentText = document.createElement( 'p' );
		contentText.classList.add( 'popup-content-label' );
		contentText.innerHTML = mw.message( 'chat-close-popup-info-label' ).text();
		contentText.setAttribute( 'role', 'alert');
		contentOptionsCnt.appendChild( contentText );

		contentCnt.appendChild( contentOptionsCnt );

		const contentBtnCnt = document.createElement( 'div' );
		contentBtnCnt.classList.add( 'popup-btn-export-cnt' );

		this.exportBtn = document.createElement( 'button' );
		this.exportBtn.classList.add( 'popup-btn' );
		this.exportBtn.textContent = mw.message( 'chat-close-popup-close-and-export-btn-label' ).text();
		this.exportBtn.addEventListener( 'click', this.onExport.bind( this ) );
		contentBtnCnt.appendChild( this.exportBtn );

		const closeBtn = document.createElement( 'button' );
		closeBtn.classList.add( 'popup-btn' );
		closeBtn.textContent = mw.message( 'chat-close-popup-close-btn-label' ).text();
		closeBtn.addEventListener( 'click', this.onClose.bind( this ) );
		contentBtnCnt.appendChild( closeBtn );

		const cancelBtn = document.createElement( 'button' );
		cancelBtn.classList.add( 'popup-btn' );
		cancelBtn.textContent = mw.message( 'chat-close-popup-cancel-btn-label' ).text();
		cancelBtn.addEventListener( 'click', this.hide.bind( this ) );
		contentBtnCnt.appendChild( cancelBtn );

		contentCnt.appendChild( contentBtnCnt );
		return contentCnt;
	}

	private onExport(): void {
		this.hide();
		this.emit( ClosePopup.EXPORT );
	}

	private onClose(): void {
		this.hide();
		this.emit( ClosePopup.CLOSE );
	}

	public disableExportButton(): void {
		this.exportBtn.setAttribute( 'disabled', 'true' );
	}

	public enableExportButton(): void {
		this.exportBtn.removeAttribute( 'disabled' );
	}
}
