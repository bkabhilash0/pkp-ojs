<template>
	<field-text
		v-bind="field"
		label="Journal Path"
		size="large"
		prefix="https://publicknowledgeproject.com/"
		@change="change"
	/>
</template>

<script>
import FieldText from '@/components/Form/fields/FieldText.vue';
import PreviewFieldBase from '../../FieldBase/previews/PreviewFieldBase.vue';
import fieldBase from '../../../helpers/field-base';
import field from '../../../helpers/field-text-given-name';

export default {
	extends: PreviewFieldBase,
	components: {
		FieldText,
	},
	data() {
		return {
			field: {
				...fieldBase,
				...field,
				isRequired: false,
			},
		};
	},
};
</script>
