import marked from "marked";

export default class MessageParser {

	public static parseMarkup( message: string ): string {
		message = marked.marked( message, { async: false } );
		// Remove <p> and </p> tags from the HTML string
		return message.replace( /<\/?p>/g, '' );
	}
}
