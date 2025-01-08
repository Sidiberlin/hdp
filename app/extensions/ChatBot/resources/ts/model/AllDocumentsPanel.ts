
import {HistoryDocument} from "../api/DeepsetApi";
import Panel from "./Panel";

export default class AllDocumentsPanel extends Panel {
	private documents: HistoryDocument[];

	public constructor( appendTo: HTMLElement, documents: HistoryDocument[] ) {
		super( mw.msg( 'chatbot-all-documents-popup-header' ), appendTo, false );
		this.documents = documents;
		this.init();
	}

	public getBody(): HTMLElement {
		const contentCnt = document.createElement( 'div' );
		this.documents.map( ( doc: HistoryDocument, index ) => {
			const anchor = document.createElement( 'a' );
			anchor.classList.add( 'document-link' );
			anchor.target = '_blank';
			anchor.href = doc.url;
			anchor.textContent = `[${index + 1}] ${doc.title}`;
			contentCnt.appendChild(	anchor );
		} ).join( '<br>' );
		return contentCnt;
	}
}
