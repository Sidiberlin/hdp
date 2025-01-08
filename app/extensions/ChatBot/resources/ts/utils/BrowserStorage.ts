import {ChatHistoryItem} from "../ChatBot";
import {ChatSizeMode} from "./Dom";

export default class BrowserStorage {

	public static readonly CHAT_HISTORY_STORAGE_KEY = 'bmbfSessionHistory';

	public static readonly SESSION_STORAGE_KEY = 'bmbfSessionId';

	public static readonly MODE_STORAGE_KEY = 'bmbfChatMode';

	public static readonly MAXIMIZED_STORAGE_KEY = 'bmbfChatMaximized';

	private static readonly CHAT_FEEDBACK_ID = 'bmbfChatFeedbackId';

	private static readonly CHAT_FEEDBACK_QUERY_ID = 'bmbfChatFeedbackQueryId';

	public getRawChatHistory(): any {
		const value = sessionStorage.getItem( BrowserStorage.CHAT_HISTORY_STORAGE_KEY );
		if ( !value ) {
			return null;
		}
		return JSON.parse( value );

	}

	public updateChatHistory( chatHistory: ChatHistoryItem[] ): void {
		try {
			sessionStorage.setItem( BrowserStorage.CHAT_HISTORY_STORAGE_KEY, JSON.stringify( chatHistory ) );
		} catch ( error ) {
			throw new Error( error.message );
		}
	}

	public getMaximizedState(): boolean {
		return !!localStorage.getItem( BrowserStorage.MAXIMIZED_STORAGE_KEY );
	}

	public setMaximized(): void {
		localStorage.setItem( BrowserStorage.MAXIMIZED_STORAGE_KEY, 'true' );
	}

	public unsetMaximized(): void {
		localStorage.removeItem( BrowserStorage.MAXIMIZED_STORAGE_KEY );
	}

	public clearAll(): void {
		sessionStorage.removeItem( BrowserStorage.CHAT_HISTORY_STORAGE_KEY );
		localStorage.removeItem( BrowserStorage.SESSION_STORAGE_KEY );
		this.clearFeedback();
	}

	public getFeedbackQueryId(): string|null {
		return localStorage.getItem( BrowserStorage.CHAT_FEEDBACK_QUERY_ID );
	}

	public getFeedbackId(): string|null {
		return localStorage.getItem( BrowserStorage.CHAT_FEEDBACK_ID );
	}

	public clearFeedback(): void {
		localStorage.removeItem( BrowserStorage.CHAT_FEEDBACK_QUERY_ID );
		localStorage.removeItem( BrowserStorage.CHAT_FEEDBACK_ID );
	}

	public setFeedbackId( id: string ): void {
		localStorage.setItem( BrowserStorage.CHAT_FEEDBACK_ID, id );
	}

	public setFeedbackQueryId( id: string ): void {
		localStorage.setItem( BrowserStorage.CHAT_FEEDBACK_QUERY_ID, id );
	}

	public getMode(): ChatSizeMode {
		return localStorage.getItem( BrowserStorage.MODE_STORAGE_KEY ) as unknown as ChatSizeMode;
	}

	public setMode( mode: string ): void {
		return localStorage.setItem( BrowserStorage.MODE_STORAGE_KEY, mode );
	}

	public getRunningSession(): string {
		return localStorage.getItem( BrowserStorage.SESSION_STORAGE_KEY );
	}

	public setRunningSession( id: string ): void {
		localStorage.setItem( BrowserStorage.SESSION_STORAGE_KEY, id );
	}
}
