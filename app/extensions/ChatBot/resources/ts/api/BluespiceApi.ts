// noinspection ES6UnusedImports
import config from "types-mediawiki/mw/config";
import ChatHistoryResponse from "./DeepsetApi";

export default class BluespiceApi {

	public downloadChatHistory( chatHistory: ChatHistoryResponse[], filename: string, format: string ): void {
		if ( format === 'pdf' ) {
			this.downloadChatHistoryPdf( chatHistory, filename );

			return;
		}

		if ( format === 'odf' ) {
			this.downloadChatHistoryOdf( chatHistory, filename );

			return;
		}

		throw new Error( `Export format ${ format } not supported` );
	}

	public async sendFeedbackMail( mailData ) {
		const url = `${ mw.config.get( 'wgScriptPath' ) }/rest.php/bmbf-feedback-mail/${mailData['resultId']}`;
		const response = await fetch( url, {
			method: 'POST',
			body: JSON.stringify( { feedback: mailData } ),
			headers: {
				'Content-Type': 'application/json'
			}
		} );
	}

	private async downloadChatHistoryPdf( chatHistory: ChatHistoryResponse[], filename: string ): Promise<void> {
		filename += '.pdf';

		const url = `${ mw.config.get( 'wgScriptPath' ) }/rest.php/bmbf-export-chat?filename=${ filename }`;
		const response = await fetch( url, {
			method: 'POST',
			body: JSON.stringify( { history: chatHistory } ),
			headers: {
				'Accept': 'application/pdf',
				'Content-Type': 'application/json'
			}
		} );

		const blob = await response.blob();
		this.executeDownloadFile( blob, filename );
	}

	private async downloadChatHistoryOdf( chatHistory: ChatHistoryResponse[], filename: string ): Promise<void> {
		filename += '.docx';

		const url = `${ mw.config.get( 'wgScriptPath' ) }/rest.php/bmbf-odf-export-chat?filename=${ filename }`;
		const response = await fetch( url, {
			method: 'POST',
			body: JSON.stringify( { history: chatHistory } ),
			headers: {
				'Accept': 'application/docx',
				'Content-Type': 'application/json'
			}
		} );

		const blob = await response.blob();
		this.executeDownloadFile( blob, filename );
	}

	private executeDownloadFile( blob: Blob, filename: string ): void {
		const blobUrl = window.URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = blobUrl;
		a.download = filename;
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		window.URL.revokeObjectURL( blobUrl );
	}
}
