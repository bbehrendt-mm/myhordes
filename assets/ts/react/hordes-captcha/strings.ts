export type TranslationStrings = {
    common: {
		title: string,
        prompt: string,
		submit: string,
		abort: string,
    },

	quizz: {
		prompt: string,
		options: {
			id: string,
			value: string,
		}[],
	},
}