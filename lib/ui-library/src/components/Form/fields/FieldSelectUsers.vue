<script>
import FieldBaseAutosuggest from './FieldBaseAutosuggest.vue';

export default {
	name: 'FieldSelectUsers',
	extends: FieldBaseAutosuggest,
	methods: {
		/**
		 * Set the suggestions from an API response
		 *
		 * Maps the API response when searching for submissions
		 * to an object with value and label props.
		 *
		 * @param {Array} newItems List of submissions
		 */
		setSuggestions(newItems) {
			const suggestions = newItems
				.filter((item) => {
					return !this.selected.find((s) => s.id === item.id);
				})
				.map((item) => {
					return {
						value: item.id,
						label: item.fullName,
					};
				});
			this.suggestions = suggestions;
		},
	},
};
</script>
