/**
 * Human-readable byte size (B / KB / MB / GB), matching the server's size_format().
 *
 * @param {number} bytes Size in bytes.
 * @return {string} Formatted size.
 */
export default function formatBytes( bytes ) {
	if ( bytes < 1024 ) {
		return `${ bytes } B`;
	}
	if ( bytes < 1024 * 1024 ) {
		return `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
	}
	if ( bytes < 1024 * 1024 * 1024 ) {
		return `${ ( bytes / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
	}
	return `${ ( bytes / ( 1024 * 1024 * 1024 ) ).toFixed( 1 ) } GB`;
}
