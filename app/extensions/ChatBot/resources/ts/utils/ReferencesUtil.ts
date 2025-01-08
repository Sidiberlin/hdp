import Reference from "../model/Reference";

export default class ReferencesUtil {

	public static insertLinks( text: string, references: Reference[] ): string {
		// Flatten references document positions with reference Id
		const flattenedReferences = references.flatMap( (item) => item.documentPositions.map( (position) => ( { position, link: item.getInlineLink() } ) )
		).sort( ( a, b ) => a.position - b.position );

		let posShift = 0;
		// Shift position in document based on previous references
		// First insert references
		flattenedReferences.forEach( ( reference ) => {
			const pos = reference.position + posShift;
			text = text.substring( 0, pos ) + reference.link + text.substring( pos );
			posShift += reference.link.length;
		} );

		return text;
	}
}
