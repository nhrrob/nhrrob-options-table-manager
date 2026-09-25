/**
 * One toolbar filter control, rendered from a plain descriptor instead of
 * hand-written markup per filter. Two descriptor shapes:
 *   { type: 'select', key, label, options: [ { value, label } ] }
 *   { type: 'lookup', key, target: 'post' | 'user', label, placeholder }
 * `type` defaults to 'select' when omitted, so existing select-only configs
 * (e.g. DataTable's status filters) don't need to change. Shared by Browse
 * (server-side filters, one per record type) and DataTable (client-side
 * filters over an already-loaded list) so a new filter — on either side —
 * is a config entry, not a new hand-rolled toolbar block.
 */
import IdLookupFilter from './IdLookupFilter';

export default function FilterControl( { descriptor, value, onChange } ) {
	if ( 'lookup' === descriptor.type ) {
		const current = value || { id: 0, label: '' };
		return (
			<IdLookupFilter
				target={ descriptor.target }
				value={ current.id }
				label={ current.label }
				placeholder={ descriptor.placeholder }
				ariaLabel={ descriptor.label }
				onChange={ ( id, label ) => onChange( { id, label } ) }
			/>
		);
	}

	return (
		<select
			className="nhrotm-browse__statusfilter"
			value={ value || '' }
			onChange={ ( e ) => onChange( e.target.value ) }
			aria-label={ descriptor.label }
		>
			{ descriptor.options.map( ( opt ) => (
				<option key={ opt.value } value={ opt.value }>
					{ opt.label }
				</option>
			) ) }
		</select>
	);
}
