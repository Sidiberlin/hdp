import Panel from "./Panel";

export default class AttachmentsPanel extends Panel {
	private attachments: string[];

	public constructor( appendTo: HTMLElement, attachments: string[] ) {
		super( mw.msg( 'chatbot-attachments-popup-header' ), appendTo, false );
		this.attachments = attachments;
		this.init();
	}

	public getBody(): HTMLElement {
		const contentCnt = document.createElement( 'div' );
		this.attachments.map( ( attachment ) => {
			const anchor = document.createElement( 'a' );
			anchor.classList.add( 'attachment-link' );
			anchor.target = '_blank';
			anchor.href = attachment;
			anchor.textContent = this.getText( attachment );
			contentCnt.appendChild(	anchor );
		} ).join( '<br>' );
		return contentCnt;
	}

	private getText( attachment: string ): string {
		const server = mw.config.get( 'wgServer' );
		if ( !attachment.startsWith( server ) ) {
			return attachment;
		}
		// Get last part since /
		const name = attachment.substring( attachment.lastIndexOf( '/' ) + 1 );
		// Decode
		return decodeURIComponent( name );
	}
}
