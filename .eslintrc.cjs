module.exports = {
  root: true,
  parser: '@typescript-eslint/parser',
  parserOptions: {
    project: ['./tsconfig.json']
  },
  plugins: ['@typescript-eslint', 'react', 'react-hooks'],
  extends: [
    'airbnb',
    'airbnb/hooks',
    'airbnb-typescript'
  ],
  ignorePatterns: ['public/**/*.js', 'dist/**/*'],
  rules: {
    'react/react-in-jsx-scope': 'off',
    'react/function-component-definition': ['error', {
      namedComponents: 'arrow-function',
      unnamedComponents: 'arrow-function'
    }],
    'no-console': ['error', { allow: ['warn', 'error'] }],
    'import/prefer-default-export': 'off',
    'react/jsx-props-no-spreading': 'off'
  }
};
