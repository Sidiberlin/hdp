import EventEmitter from "events";
import { ChatHistoryItem, FeedbackEvent } from "../ChatBot";
import ReferencesUtil from "../utils/ReferencesUtil";
import ReferenceFactory, { ReferenceDocumentMeta } from "../utils/ReferenceFactory";
import Reference from "../model/Reference";
import BrowserStorage from "../utils/BrowserStorage";

export interface ResponseReference {
	document_id: string
	document_position: number
	answer_start_idx: number
	answer_end_idx: number
	doc_start_idx: number
	doc_end_idx: number
}

export interface ResponseMeta {
	_references?: ResponseReference[]
}

export interface ReferenceDocument {
	id: string
	file: {
		name: string
	},
	meta: ReferenceDocumentMeta
}

export interface ChatResponse {
	query_id: string
	result: {
		query: string
		answers: {
			answer: string
			meta: ResponseMeta
			result_id: string
		}[],
		documents: ReferenceDocument[]
	};
	type: string
}

export interface QueryAnswerQuery {
	type: string
	followUpType?: string
	query: string
}

export interface QueryAnswer {
	query: QueryAnswerQuery
	answer: string
	result_id: string
	query_id: string
	references: Reference[]
	allDocuments: HistoryDocument[]
}

interface SearchHistoryResponse {
	request: {
		query: string
	}
	response: {
		documents: ReferenceDocument[]
		result: {
			answer: string
			meta: ResponseMeta
		}
	}[]
	search_history_id: string
	session_id: string
	time: string
}

export interface HistoryDocument {
	id: string
	title: string
	url: string
}

export interface StreamFetchData {
	query: string,
	sessionId: string,
	followUpType?: string
}

export interface StreamDeltaObject {
	query_id: string;
	delta: { text: string };
	type: string;
}


export default class DeepsetApi extends EventEmitter {
	private readonly chatUrl: string;

	private readonly historyUrl: string;

	private readonly sessionUrl: string;

	private readonly feedbackUrl: string;

	public static readonly EVENT_ERROR: string = "error";

	public static readonly STREAM_EVENT_DELTA: string = "delta";

	private browserStorage: BrowserStorage;

	public constructor(
		chatUrl: string,
		historyUrl: string,
		sessionUrl: string,
		feedbackUrl: string,
	) {
		super();

		this.chatUrl = chatUrl;
		this.historyUrl = historyUrl;
		this.sessionUrl = sessionUrl;
		this.feedbackUrl = feedbackUrl;
		this.browserStorage = new BrowserStorage();
	}

	public async getChatHistory( sessionId: string ): Promise<ChatHistoryItem[]> {
		const result = [];
		let hasMore = true;
		while ( hasMore ) {
			const { data, has_more } = await this.fetchChatHistory( sessionId, result.length - 1 );
			hasMore = has_more;
			result.push( ...data );
		}

		// Strip the response down to the relevant data
		return result.map( ( entry: SearchHistoryResponse ) => {
			const response = entry.response[ 1 ];
			const references = ReferenceFactory.createFromResponseData(
				response.result.meta,
				response.documents
			);

			if ( references.missing.length > 0 ) {
				this.emit(
					DeepsetApi.EVENT_ERROR,
					mw.message( 'chat-api-references-not-found-error', references.missing.join( ', ' ) ).text()
				);
			}

			const validReferences = references.valid;
			const requestQuery = this.convertHistoryRequest( entry.request );

			return {
				query: requestQuery,
				answer: ReferencesUtil.insertLinks( response.result.answer, validReferences ),
				session_id: entry.session_id,
				references: validReferences,
				time: entry.time,
				result_id: null,
				query_id: null,
				allDocuments: this.prepareAllDocuments( response.documents )
			};
		} ).reverse() as ChatHistoryItem[];
	}

	private convertHistoryRequest( request ): QueryAnswerQuery {
		const path = request.params.ConditionalRouter.path || 'rag';
		const query = request.query;

		if ( path === 'rag' ) {
			return { type: 'normal', query: query };
		}
		return { type: 'followUp', followUpType: path, query: query };
	}

	private async fetchChatHistory( sessionId: string, after: number = -1 ): Promise<{
		data: SearchHistoryResponse[],
		has_more: boolean
	}> {
		const response = await fetch( this.historyUrl, {
			method: 'POST',
			body: JSON.stringify( {
				sessionId: sessionId,
				after: after
			} ),
		} );
		const jsonResponse = await response.json();

		if ( jsonResponse.errors ) {
			this.emit(
				DeepsetApi.EVENT_ERROR,
				mw.message( 'chat-api-error', jsonResponse.errors.join( '. ' ) ).text()
			);
		}

		return jsonResponse;
	}

	public async sendMessage( query: string, sessionId: string, followUpType?: string ): Promise<QueryAnswer> {
		const chatResponse = await this.stream( this.chatUrl, {
			query,
			sessionId,
			followUpType
		} );
		return this.processQueryAnswer( chatResponse, followUpType );
	}

	private async stream( url: string, data: StreamFetchData ): Promise<ChatResponse> {
		return new Promise( ( resolve, reject ) => {
			let urlParams = "?query=" + data.query + "&sessionId=" + data.sessionId;

			if ( data.followUpType ) {
				urlParams += "&followUpType=" + data.followUpType;
			}

			const streaming = new EventSource( url + urlParams );
			streaming.onmessage = ( event: MessageEvent ) => {
				const streamObject = JSON.parse( event.data ) as StreamDeltaObject | ChatResponse;
				if ( streamObject.type === 'delta' ) {
					this.emit( DeepsetApi.STREAM_EVENT_DELTA, streamObject, data );
				} else if ( streamObject.type === 'result' ) {
					streaming.close();
					resolve( streamObject as ChatResponse );
				}
			};
			streaming.onerror = ( error ) => {
				streaming.close();
				console.error( error );
				this.emit(
					DeepsetApi.EVENT_ERROR,
					mw.message( 'chat-api-load-error', error ).text()
				);
				reject( error );
			};
		} );
	}

	private processQueryAnswer( data: ChatResponse, followUpType?: string ): QueryAnswer {
		const result = data.result;
		// ERM38705 Es werden nun auch im Antwort array 2 objekte ausgegeben.
		// Das 1. ist die umformulierte Frage, das 2. ist die Antwort.
		const answer = result.answers[ 1 ];

		const references = ReferenceFactory.createFromResponseData(
			answer.meta,
			result.documents
		);

		if ( references.missing.length > 0 ) {
			this.emit(
				DeepsetApi.EVENT_ERROR,
				mw.message( 'chat-api-references-not-found-error', references.missing.join( ', ' ) ).text()
			);
		}

		const validReferences = references.valid;
		const query = followUpType ?
			{ type: 'followUp', followUpType: followUpType, query: result.query } :
			{ type: 'normal', query: result.query };
		return {
			query: query,
			query_id: data.query_id,
			result_id: answer.result_id,
			answer: ReferencesUtil.insertLinks( answer.answer, validReferences ),
			references: validReferences,
			allDocuments: this.prepareAllDocuments( result.documents )
		} as QueryAnswer;
	}

	private prepareAllDocuments( documents: ReferenceDocument[] ): HistoryDocument[] {
		return documents.map( ( document ) => {
			return {
				id: document.id,
				title: document.meta.prefixed_title,
				url: document.meta.uri
			} as HistoryDocument;
		} );
	}

	public async sendFeedback( feedback: FeedbackEvent, sessionId: string ): Promise<void> {
		const feedbackQueryId = this.browserStorage.getFeedbackQueryId();
		let feedbackID = '';
		if ( feedbackQueryId && feedbackQueryId === feedback.queryId ) {
			feedbackID = this.browserStorage.getFeedbackId();
			if ( !feedbackID ) {
				feedbackID = '';
			}
		} else {
			this.browserStorage.clearFeedback();
		}

		const response = await fetch( `${ this.feedbackUrl + '/' + feedbackID }`, {
			method: 'POST',
			body: JSON.stringify( {
				sessionId: sessionId,
				feedback: JSON.stringify( {
					result_id: feedback.resultId,
					query_id: feedback.queryId,
					score: feedback.score,
					comment: feedback.comment,
					tags: feedback.tags
				} )
			} )
		} );

		const jsonResponse = await response.json();

		if ( jsonResponse.feedback_id ) {
			this.browserStorage.setFeedbackId( jsonResponse.feedback_id );
			this.browserStorage.setFeedbackQueryId( feedback.queryId );
		}

		if ( jsonResponse.errors ) {
			this.emit(
				DeepsetApi.EVENT_ERROR,
				mw.message( 'chat-api-error', jsonResponse.errors.join( '. ' ) ).text()
			);
		}
	}

	public async fetchSessionId(): Promise<string> {
		const response = await fetch( this.sessionUrl );
		const jsonResponse = await response.json();

		if ( jsonResponse.errors ) {
			this.emit(
				DeepsetApi.EVENT_ERROR,
				mw.message( 'chat-api-error', jsonResponse.errors.join( '. ' ) ).text()
			);
		}

		return jsonResponse.search_session_id;
	}
}
