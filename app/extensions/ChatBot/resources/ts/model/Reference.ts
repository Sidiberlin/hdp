import {ReferenceDocumentMeta} from "../utils/ReferenceFactory";
import EventEmitter from "events";
import AttachmentsPanel from "./AttachmentsPanel";

export default class Reference extends EventEmitter {
	public docRefId: number;

	public meta: ReferenceDocumentMeta;

	private attachmentsPanel: AttachmentsPanel;

	public title: mw.Title;

	public documentPositions: number[];

	public constructor( docRefId: number, meta: ReferenceDocumentMeta ) {
		super();
		this.docRefId = docRefId;
		this.meta = meta;
		this.title = mw.Title.newFromText( meta.prefixed_title );
		this.documentPositions = [];
	}

	public addDocumentPosition( pos: number ): void {
		this.documentPositions.push( pos );
	}

	public getInlineLink(): string {
		return this.getLink( `[${ this.docRefId }]` );
	}

	public getLinkListRefId(): string {
		return this.getHtmlLink( `[${ this.docRefId }]` );
	}

	public getLinkListItem(): string {
		const section = this.getSection();
		const sectionAnchor = section ? `/${ section }` : '';

		return this.getHtmlLink( this.title.getMainText() + sectionAnchor, 'reference-link' );
	}

	public getAttachments(): HTMLElement {
		const btnCnt = document.createElement( 'div' );
		btnCnt.classList.add( 'reference-attachments' );
		if ( !this.meta.attachments || this.meta.attachments.length === 0 ) {
			return btnCnt;
		}
		if ( !this.attachmentsPanel ) {
			this.attachmentsPanel = new AttachmentsPanel( btnCnt, this.meta.attachments );
			this.attachmentsPanel.on( 'cancel', () => {
				btn.hidden = false;
			} );
		}
		// Create a button to open popup
		const btn = document.createElement( 'button' );
		btn.textContent = mw.message( 'chat-reference-attachments' ).text();
		btn.classList.add( 'attachments-button' );
		btn.setAttribute( 'aria-expanded', "false" );
		btn.addEventListener( 'click', () => {
			this.attachmentsPanel.toggle();
			if ( this.attachmentsPanel.isOpen() ) {
				btn.setAttribute( 'aria-expanded', "true" );
				btn.hidden = true;
			} else {
				btn.setAttribute( 'aria-expanded', "false" );
			}
		} );
		btnCnt.appendChild( btn );
		return btnCnt;
	}

	public closeAttachments(): void {
		if ( this.attachmentsPanel ) {
			this.attachmentsPanel.hide();
		}
	}

	private getLink( text: string ): string {
		const section = this.getSection();
		const sectionAnchor = section ? `#${ section }` : '';

		return `[${ text }](${ this.title.getUrl() + sectionAnchor } "${ this.title.getPrefixedText() }")`;
	}

	private getHtmlLink( text: string, cls?: string ): string {
		const classes = cls ? [ cls ] : [];
		if ( this.title.exists() === false ) {
			classes.push( 'missing' );
		}
		const clsString = `class="${ classes }"`;

		const section = this.getSection();
		const sectionAnchor = section ? `#${ section }` : '';

		return `<a title="${ this.title.getPrefixedText() + sectionAnchor }" ${ clsString }" href="${ this.title.getUrl() + sectionAnchor }">${ text }</a>`;
	}

	private getSection(): string|null {
		if ( this.meta.sections && this.meta.sections.length > 0 ) {
			return this.meta.sections[ 0 ];
		}
		return null;
	}

	toJSON() {
		return {
			docRefId: this.docRefId,
			meta: this.meta
		};
	}
}
